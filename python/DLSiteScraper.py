import sys
import os
import logging
import asyncio
import json
from dlsite_async import DlsiteAPI

storageDir = sys.argv[2]

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
        return await api.get_work("RJ01103906")


async def englishDLsite():
    async with DlsiteAPI(locale="en_US") as api:
        return await api.get_work("RJ01103906")

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


    logging.info(f""+workJapanese.product_id+" completed")
except Exception as error:
    logging.error(f"Error occurred:\n{error}")

