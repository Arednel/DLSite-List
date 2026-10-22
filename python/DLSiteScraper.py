import asyncio
import argparse
import json
import logging
import os
import re
import sys
import time
from enum import Enum
from pathlib import Path
from urllib.parse import urljoin, urlsplit

import requests
from dlsite_async import DlsiteAPI
from dlsite_async.work import WorkOption, WorkType
from PIL import Image

from weekly_logging import WeeklyFileHandler

ADDITIONAL_PRODUCT_FORMAT_OPTIONS = {
    WorkOption.VOICE,
    WorkOption.MUSIC,
    WorkOption.VIDEO,
}

MIRRORED_PRODUCT_FORMAT_OPTIONS = {
    WorkOption.VOICE: WorkType.VOICE_ASMR,
    WorkOption.MUSIC: WorkType.MUSIC,
    WorkOption.VIDEO: WorkType.VIDEO,
}

# Image downloads only follow DLsite-owned hosts and stay within fixed resource budgets.
ALLOWED_IMAGE_HOST_SUFFIXES = ("dlsite.jp", "dlsite.com")
MAX_IMAGE_REDIRECTS = 3
MAX_IMAGE_BYTES = 20 * 1024 * 1024
MAX_IMAGE_DOWNLOAD_SECONDS = 60
MAX_IMAGE_DIMENSION = 20000
MAX_IMAGE_PIXELS = 50_000_000
MAX_SAMPLE_IMAGES = 50


def parse_arguments(argv=None):
    parser = argparse.ArgumentParser()
    parser.add_argument("--work-id", required=True)
    parser.add_argument("--json-output", required=True)
    parser.add_argument("--log-directory", required=True)
    parser.add_argument("--image-output")
    return parser.parse_args(argv)


def configure_logging(log_directory):
    logging.basicConfig(
        handlers=[
            WeeklyFileHandler(
                Path(log_directory),
                "DLSiteScraper",
                retention_days=os.getenv("LOG_RETENTION_DAYS"),
            )
        ],
        level=logging.DEBUG,
        format="%(asctime)s \n%(message)s",
        datefmt="[%Y-%m-%d] [%H:%M:%S]",
        force=True,
    )


def to_serializable(obj):
    if isinstance(obj, WorkOption):
        return obj.value
    if isinstance(obj, Enum):
        return {
            "_value_": to_serializable(obj.value),
            "_name_": obj.name,
        }
    if isinstance(obj, dict):
        return {k: to_serializable(v) for k, v in obj.items()}
    elif isinstance(obj, list):
        return [to_serializable(i) for i in obj]
    elif hasattr(obj, "model_dump"):
        return to_serializable(obj.model_dump())
    elif hasattr(obj, "__dict__"):
        return to_serializable(vars(obj))
    elif isinstance(obj, (str, int, float, bool)) or obj is None:
        return obj
    else:
        return str(obj)  # Fallback for anything weird


def product_format_codes(work):
    formats = []
    work_type = getattr(work, "work_type", None)

    if isinstance(work_type, WorkType):
        formats.append(work_type.value)

    for option in getattr(work, "options", None) or []:
        if (
            option in ADDITIONAL_PRODUCT_FORMAT_OPTIONS
            and MIRRORED_PRODUCT_FORMAT_OPTIONS[option] != work_type
            and option.value not in formats
        ):
            formats.append(option.value)

    return formats


async def japanese_dlsite(work_id):
    async with DlsiteAPI() as api:
        return await api.get_work(work_id)


async def english_dlsite(work_id):
    async with DlsiteAPI(locale="en_US") as api:
        return await api.get_work(work_id)


async def fetch_work_data(work_id):
    japanese = await japanese_dlsite(work_id)
    english = await english_dlsite(work_id)

    return japanese, english


def normalize_image_url(url):
    if not isinstance(url, str):
        return None

    url = url.strip()

    # Fix protocol-relative and site-relative URLs
    if url.startswith("//"):
        url = "https:" + url
    elif url.startswith("/"):
        url = "https://www.dlsite.com" + url

    return url if is_allowed_image_url(url) else None


def is_allowed_image_url(url):
    try:
        parts = urlsplit(url)
        port = parts.port
    except ValueError:
        return False

    host = (parts.hostname or "").lower().rstrip(".")

    if parts.scheme not in ("https", "http") or port is not None:
        return False
    if parts.username is not None or parts.password is not None:
        return False

    return any(
        host == suffix or host.endswith("." + suffix)
        for suffix in ALLOWED_IMAGE_HOST_SUFFIXES
    )


def open_image_response(url, headers):
    current_url = url

    for _ in range(MAX_IMAGE_REDIRECTS + 1):
        if not is_allowed_image_url(current_url):
            raise IOError(f"Image URL is not on an allowed DLsite host: {current_url}")

        response = requests.get(
            current_url,
            stream=True,
            timeout=15,
            headers=headers,
            allow_redirects=False,
        )

        if not response.is_redirect:
            return response

        location = response.headers.get("Location")
        response.close()

        if not location:
            raise IOError("Image redirect has no location")

        current_url = urljoin(current_url, location)

    raise IOError("Too many image redirects")


def verify_image_file(path):
    with Image.open(path) as image:
        width, height = image.size

        if (
            width <= 0
            or height <= 0
            or width > MAX_IMAGE_DIMENSION
            or height > MAX_IMAGE_DIMENSION
            or width * height > MAX_IMAGE_PIXELS
        ):
            raise IOError(f"Image dimensions exceed the safety limit: {width}x{height}")

        image.verify()

    with Image.open(path) as image:
        image.load()


def download_image(url, save_path):
    url = normalize_image_url(url)

    if url is None:
        logging.warning("Download skipped for an image URL outside DLsite hosts")
        return False

    headers = {
        "User-Agent": (
            "Mozilla/5.0 (Windows NT 10.0; Win64; x64) "
            "AppleWebKit/537.36 (KHTML, like Gecko) "
            "Chrome/115.0.0.0 Safari/537.36"
        )
    }

    temporary_path = save_path.with_name(f"{save_path.name}.part")

    try:
        logging.debug(f"Downloading {url}")
        deadline = time.monotonic() + MAX_IMAGE_DOWNLOAD_SECONDS

        with open_image_response(url, headers) as response:
            response.raise_for_status()

            declared_length = response.headers.get("Content-Length", "")
            if declared_length.isdigit() and int(declared_length) > MAX_IMAGE_BYTES:
                raise IOError("Image exceeds the download size limit")

            save_path.parent.mkdir(parents=True, exist_ok=True)
            written = 0
            with open(temporary_path, "wb") as file:
                for chunk in response.iter_content(65536):
                    written += len(chunk)

                    if written > MAX_IMAGE_BYTES:
                        raise IOError("Image exceeds the download size limit")
                    if time.monotonic() > deadline:
                        raise IOError("Image download exceeded the time limit")

                    file.write(chunk)

        if os.path.getsize(temporary_path) == 0:
            raise IOError("Downloaded file is empty")

        try:
            verify_image_file(temporary_path)
        except Exception as error:
            raise IOError(f"Corrupted, incomplete or oversized image: {error}")

        os.replace(temporary_path, save_path)
        logging.info(f"Downloaded {url} → {save_path}")
        return True
    except Exception as error:
        if temporary_path.exists():
            temporary_path.unlink()
        logging.warning(f"Download failed for {url}: {error}")
        return False


def map_known_error(error_message: str, work_id: str):
    """
    Returns (user_message, exit_code) for known errors, else None.
    Keep user_message to ONE LINE so Laravel can display it cleanly.
    """
    msg = (error_message or "").strip()

    patterns = [
        (f"Failed to get product info for {work_id}", "GeoBlocked DLsite work", 2),
        (f"Not Found", "This work was deleted or could not be found on DLsite", 2),
        (f"Bad Request", "This work could not be found on DLsite", 2),
    ]

    for pattern, user_message, exit_code in patterns:
        if re.search(pattern, msg, flags=re.IGNORECASE):
            return user_message, exit_code

    return None


def limit_sample_images(work):
    sample_images = work.get("sample_images")

    if isinstance(sample_images, list) and len(sample_images) > MAX_SAMPLE_IMAGES:
        logging.warning(
            f"Keeping the first {MAX_SAMPLE_IMAGES} of {len(sample_images)} sample images"
        )
        work["sample_images"] = sample_images[:MAX_SAMPLE_IMAGES]

    return work


def fetch_images(work, image_output):
    downloaded_images = []
    failed_images = []
    image_output.mkdir(parents=True, exist_ok=True)
    image_targets = [
        ("cover.jpg", work.get("work_image")),
        *[
            (f"sample_{index}.jpg", url)
            for index, url in enumerate(
                (work.get("sample_images") or [])[:MAX_SAMPLE_IMAGES], start=1
            )
        ],
    ]

    for filename, url in image_targets:
        if url and download_image(url, image_output / filename):
            downloaded_images.append(filename)
        else:
            failed_images.append(filename)

    return downloaded_images, failed_images


def run(arguments):
    work_japanese, work_english = asyncio.run(fetch_work_data(arguments.work_id))
    japanese = limit_sample_images(to_serializable(work_japanese))
    english = limit_sample_images(to_serializable(work_english))
    japanese["product_format"] = product_format_codes(work_japanese)
    english["product_format"] = product_format_codes(work_english)
    combined = {"japanese": japanese, "english": english}
    json_output = Path(arguments.json_output)
    json_output.parent.mkdir(parents=True, exist_ok=True)

    with open(json_output, "w", encoding="utf-8") as f:
        json.dump(combined, f, ensure_ascii=False, indent=2)

    downloaded_images = []
    failed_images = []

    if arguments.image_output:
        downloaded_images, failed_images = fetch_images(
            japanese,
            Path(arguments.image_output),
        )

    product_id = japanese.get("product_id") or arguments.work_id
    logging.info(f"{product_id} completed")

    return {
        "product_id": product_id,
        "json_path": str(json_output),
        "downloaded_images": downloaded_images,
        "failed_images": failed_images,
    }


def main(argv=None):
    arguments = parse_arguments(argv)
    configure_logging(arguments.log_directory)

    try:
        print(json.dumps(run(arguments), ensure_ascii=False))
        return 0
    except Exception as error:
        error_message = str(error).strip()
        logging.error(f"Error occurred:\n{error}")
        mapped = map_known_error(error_message, arguments.work_id)

        if mapped:
            user_message, exit_code = mapped
            print(user_message, file=sys.stderr)
            return exit_code

        print(error_message, file=sys.stderr)
        return 1


if __name__ == "__main__":
    sys.exit(main())
