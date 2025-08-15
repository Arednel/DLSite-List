import sys
import os
import logging
import asyncio
import json
from dlsite_async import DlsiteAPI
import requests

storageDir = sys.argv[2]
workID = sys.argv[3]

# Set the log file path and name
log_file = os.path.join(f"{storageDir}\\logs\\DLSiteScraper.log")
# Configure logging
logging.basicConfig(
    filename=log_file,
    level=logging.DEBUG,
    format="%(asctime)s \n%(message)s",
    datefmt="[%Y-%m-%d] [%H:%M:%S]",
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
    
async def japaneseDLsite():
    async with DlsiteAPI() as api:
        return await api.get_work(workID)


async def englishDLsite():
    async with DlsiteAPI(locale="en_US") as api:
        return await api.get_work(workID)

def download_image(url, save_path):
    try:
        # Fix protocol-relative URLs
        if url.startswith("//"):
            url = "https:" + url
        elif url.startswith("/"):
            url = "https://www.dlsite.com" + url

        logging.debug(f"Attempting to download image: {url}")

        headers = {
            "User-Agent": (
                "Mozilla/5.0 (Windows NT 10.0; Win64; x64) "
                "AppleWebKit/537.36 (KHTML, like Gecko) "
                "Chrome/115.0.0.0 Safari/537.36"
            )
        }

        response = requests.get(url, stream=True, timeout=15, headers=headers)
        response.raise_for_status()

        # Save to file
        with open(save_path, "wb") as f:
            for chunk in response.iter_content(8192):
                f.write(chunk)

        logging.info(f"Downloaded {url} → {save_path}")
    except Exception as e:
        logging.error(f"Failed to download {url}: {e}")

try:
    workJapanese = asyncio.run(japaneseDLsite())

    workEnglish = asyncio.run(englishDLsite())

    # Convert to serializable dicts
    workJapanese_serialized = to_serializable(workJapanese)
    workEnglish_serialized = to_serializable(workEnglish)

    # Combine and save
    combined = {
        "japanese": workJapanese_serialized,
        "english": workEnglish_serialized
    }

    # Define save path and filename
    savePath = f"{storageDir}\\app\\Works\\"
    os.makedirs(savePath, exist_ok=True)  # Create folder if it doesn't exist
    filename = f"{workJapanese.product_id}.json"

    # Save JSON
    with open(os.path.join(savePath, filename), "w", encoding="utf-8") as f:
        json.dump(combined, f, ensure_ascii=False, indent=2)
    
    # After saving JSON
    images_dir = os.path.join(storageDir, "app", "public", "Works", workJapanese.product_id)
    os.makedirs(images_dir, exist_ok=True)

    # Main cover
    cover_url = workJapanese_serialized["work_image"]
    cover_path = os.path.join(images_dir, "cover.jpg")
    download_image(cover_url, cover_path)

    # Sample images
    for idx, img_url in enumerate(workJapanese_serialized.get("sample_images", []), start=1):
        img_path = os.path.join(images_dir, f"sample_{idx}.jpg")
        download_image(img_url, img_path)

    logging.info(f""+workJapanese.product_id+" completed")
except Exception as error:
    logging.error(f"Error occurred:\n{error}")

