import asyncio
import json
import re
import sys

from dlsite_async import DlsiteAPI

workID = sys.argv[1]


def to_serializable(obj):
    if isinstance(obj, dict):
        return {k: to_serializable(v) for k, v in obj.items()}
    if isinstance(obj, list):
        return [to_serializable(i) for i in obj]
    if hasattr(obj, "model_dump"):
        return to_serializable(obj.model_dump())
    if hasattr(obj, "__dict__"):
        return to_serializable(vars(obj))
    if isinstance(obj, (str, int, float, bool)) or obj is None:
        return obj

    return str(obj)


async def japanese_dlsite():
    async with DlsiteAPI() as api:
        return await api.get_work(workID)


async def english_dlsite():
    async with DlsiteAPI(locale="en_US") as api:
        return await api.get_work(workID)


def map_known_error(error_message: str, work_id: str):
    msg = (error_message or "").strip()

    patterns = [
        (f"Failed to get product info for {work_id}", "GeoBlocked DLSite work", 2),
        ("Not Found", "Deleted or Non-existing DLSite work", 2),
        ("Bad Request", "Non-existing DLSite work", 2),
    ]

    for pattern, user_message, exit_code in patterns:
        if re.search(pattern, msg, flags=re.IGNORECASE):
            return user_message, exit_code

    return None


try:
    work_japanese = to_serializable(asyncio.run(japanese_dlsite()))
    work_english = to_serializable(asyncio.run(english_dlsite()))

    print(json.dumps({
        "japanese": {
            "genre": work_japanese.get("genre") or [],
        },
        "english": {
            "genre": work_english.get("genre") or [],
        },
    }, ensure_ascii=True))
except Exception as error:
    error_message = str(error).strip()
    mapped = map_known_error(error_message, workID)

    if mapped:
        user_message, exit_code = mapped
        print(user_message, file=sys.stderr)
        sys.exit(exit_code)

    print(error_message, file=sys.stderr)
    sys.exit(1)
