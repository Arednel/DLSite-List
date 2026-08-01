import asyncio
import argparse
import json
import logging
import os
import re
import sys
from pathlib import Path

import requests
from dlsite_async import DlsiteAPI
from PIL import Image

from weekly_logging import WeeklyFileHandler


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


def download_image(url, save_path):
    # Fix protocol-relative URLs
    if url.startswith("//"):
        url = "https:" + url
    elif url.startswith("/"):
        url = "https://www.dlsite.com" + url

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
        response = requests.get(url, stream=True, timeout=15, headers=headers)
        response.raise_for_status()

        save_path.parent.mkdir(parents=True, exist_ok=True)
        with open(temporary_path, "wb") as file:
            for chunk in response.iter_content(8192):
                file.write(chunk)

        if os.path.getsize(temporary_path) == 0:
            raise IOError("Downloaded file is empty")

        try:
            with Image.open(temporary_path) as image:
                image.verify()
            with Image.open(temporary_path) as image:
                image.load()
        except Exception as error:
            raise IOError(f"Corrupted or incomplete image: {error}")

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
        (f"Failed to get product info for {work_id}", "GeoBlocked DLSite work", 2),
        (f"Not Found", "Deleted or Non-existing DLSite work", 2),
        (f"Bad Request", "Non-existing DLSite work", 2),
    ]

    for pattern, user_message, exit_code in patterns:
        if re.search(pattern, msg, flags=re.IGNORECASE):
            return user_message, exit_code

    return None


def fetch_images(work, image_output):
    downloaded_images = []
    failed_images = []
    image_output.mkdir(parents=True, exist_ok=True)
    image_targets = [
        ("cover.jpg", work.get("work_image")),
        *[
            (f"sample_{index}.jpg", url)
            for index, url in enumerate(work.get("sample_images") or [], start=1)
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
    japanese = to_serializable(work_japanese)
    english = to_serializable(work_english)
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
