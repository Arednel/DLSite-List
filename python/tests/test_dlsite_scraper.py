import asyncio
import sys
import unittest
from pathlib import Path
from types import SimpleNamespace

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

from dlsite_async.work import AgeCategory, BookType, WorkOption, WorkType
from DLSiteScraper import product_format_codes, rating_fields, to_serializable


class DLSiteScraperProductFormatTest(unittest.TestCase):
    def work(self, work_type=None, options=None):
        return SimpleNamespace(work_type=work_type, options=options)

    def test_additional_voice_music_and_animation_options_use_their_real_codes(self):
        self.assertEqual(
            ["ACN", "SND", "MS2", "MV2"],
            product_format_codes(
                self.work(
                    WorkType.ACTION,
                    [WorkOption.VOICE, WorkOption.MUSIC, WorkOption.VIDEO],
                )
            ),
        )

    def test_additional_formats_are_suppressed_when_their_mirrored_main_exists(self):
        cases = [
            (WorkType.MUSIC, WorkOption.MUSIC, ["MUS"]),
            (WorkType.VOICE_ASMR, WorkOption.VOICE, ["SOU"]),
            (WorkType.VIDEO, WorkOption.VIDEO, ["MOV"]),
        ]

        for work_type, option, expected in cases:
            with self.subTest(work_type=work_type):
                self.assertEqual(
                    expected,
                    product_format_codes(self.work(work_type, [option])),
                )

    def test_additional_formats_are_kept_when_their_mirrored_main_is_absent(self):
        self.assertEqual(
            ["SND", "MS2", "MV2"],
            product_format_codes(
                self.work(
                    options=[WorkOption.VOICE, WorkOption.MUSIC, WorkOption.VIDEO],
                )
            ),
        )

    def test_non_format_options_are_ignored(self):
        self.assertEqual(
            ["SOU"],
            product_format_codes(
                self.work(
                    WorkType.VOICE_ASMR,
                    [WorkOption.ENGLISH, WorkOption.AI_GENERATED, WorkOption.REVIEWS],
                )
            ),
        )

    def test_additional_formats_preserve_option_order(self):
        self.assertEqual(
            ["ACN", "MS2", "SND", "MV2"],
            product_format_codes(
                self.work(
                    WorkType.ACTION,
                    [WorkOption.MUSIC, WorkOption.VOICE, WorkOption.VIDEO],
                )
            ),
        )

    def test_all_main_product_formats_are_stored_without_special_cases(self):
        for work_type in WorkType:
            with self.subTest(work_type=work_type):
                self.assertEqual(
                    [work_type.value],
                    product_format_codes(self.work(work_type)),
                )

    def test_work_option_serializes_to_its_stable_code(self):
        self.assertEqual("SND", to_serializable(WorkOption.VOICE))

    def test_existing_enum_serialization_contract_excludes_python_internals(self):
        cases = [
            (AgeCategory.R18, {"_value_": 3, "_name_": "R18"}),
            (WorkType.MANGA, {"_value_": "MNG", "_name_": "MANGA"}),
            (BookType.BOOK, {"_value_": "comic", "_name_": "BOOK"}),
        ]

        for enum_value, expected in cases:
            with self.subTest(enum_value=enum_value):
                self.assertEqual(expected, to_serializable(enum_value))

    def test_serialized_enums_do_not_include_python_enum_internals(self):
        serialized = to_serializable(
            SimpleNamespace(
                age_category=AgeCategory.ALL_AGES,
                work_type=WorkType.MANGA,
                book_type=BookType.BOOK,
            )
        )

        self.assertNotIn("__objclass__", str(serialized))
        self.assertNotIn("_sort_order_", str(serialized))


class FakeResponse:
    def __init__(self, payload):
        self.payload = payload

    async def __aenter__(self):
        return self

    async def __aexit__(self, *args):
        return False

    def raise_for_status(self):
        if isinstance(self.payload, Exception):
            raise self.payload

    async def json(self, content_type=None):
        return self.payload


class FakeApi:
    def __init__(self, payload):
        self.payload = payload
        self.requests = []

    def get(self, url, params=None):
        self.requests.append((url, params))
        return FakeResponse(self.payload)


class DLSiteScraperRatingTest(unittest.TestCase):
    product_id = "RJ01741555"

    def rating(self, payload):
        return asyncio.run(rating_fields(FakeApi(payload), self.product_id))

    def test_rating_fields_keep_the_dlsite_average_and_count(self):
        api = FakeApi(
            {
                self.product_id: {
                    "rate_average": 5,
                    "rate_average_2dp": 4.96,
                    "rate_average_star": 50,
                    "rate_count": 57,
                }
            }
        )

        self.assertEqual(
            {"rate_average_2dp": 4.96, "rate_count": 57},
            asyncio.run(rating_fields(api, self.product_id)),
        )
        self.assertEqual(
            [
                (
                    "https://www.dlsite.com/maniax/product/info/ajax",
                    {"product_id": self.product_id},
                )
            ],
            api.requests,
        )

    def test_unrated_works_report_no_rating(self):
        self.assertEqual(
            {"rate_average_2dp": None, "rate_count": None},
            self.rating(
                {
                    self.product_id: {
                        "rate_average": None,
                        "rate_average_2dp": None,
                        "rate_count": None,
                    }
                }
            ),
        )

    def test_rating_is_unknown_when_the_request_fails(self):
        self.assertIsNone(self.rating(RuntimeError("offline")))

    def test_rating_is_unknown_when_dlsite_returns_no_work_or_fields(self):
        # DLsite answers [] for a work it cannot find.
        self.assertIsNone(self.rating([]))
        self.assertIsNone(self.rating({"RJ00000001": {"rate_count": 3}}))
        self.assertIsNone(self.rating({self.product_id: {"dl_count": 3}}))


if __name__ == "__main__":
    unittest.main()
