import sys
import unittest
from pathlib import Path
from types import SimpleNamespace

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

import asyncio
from dataclasses import replace

from dlsite_async.work import AgeCategory, BookType, Work, WorkOption, WorkType
from DLSiteScraper import (
    fill_from_product_api,
    missing_product_api_fields,
    product_api_details,
    product_format_codes,
    to_serializable,
)


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


AIX_PRODUCT = {
    "workno": "RJ01190426",
    "maker_id": "RG01003097",
    "maker_name": "DIXY",
    "genres": [
        {"name": "萌え", "id": 51},
        {"name": "学生", "id": 400},
    ],
    "image_samples": [
        {"url": "//img.dlsite.jp/RJ01190426_img_smp1.jpg"},
        {"url": "//img.dlsite.jp/RJ01190426_img_smp2.jpg"},
    ],
}


class FakeResponse:
    def __init__(self, payload):
        self.payload = payload

    async def __aenter__(self):
        return self

    async def __aexit__(self, *args):
        return False

    async def json(self, content_type=None):
        return self.payload


class FakeAPI:
    def __init__(self, payload):
        self.payload = payload
        self.requests = []

    def get(self, url, params=None):
        self.requests.append((url, params))
        return FakeResponse(self.payload)


class DLSiteScraperProductApiFallbackTest(unittest.TestCase):
    def aix_work(self, **fields):
        return replace(
            Work(
                product_id="RJ01190426",
                site_id="aix",
                maker_id="RG01003097",
                work_name="Play! With Mari",
                age_category=AgeCategory.R18,
            ),
            **fields,
        )

    def test_guest_aix_work_reports_missing_maker_genre_and_samples(self):
        self.assertEqual(
            ["maker", "genre", "sample_images"],
            missing_product_api_fields(self.aix_work()),
        )

    def test_complete_work_reports_nothing_missing(self):
        work = self.aix_work(
            circle="DIXY",
            genre=["萌え"],
            sample_images=["//img.dlsite.jp/smp1.jpg"],
        )

        self.assertEqual([], missing_product_api_fields(work))

    def test_circle_maker_maps_to_circle(self):
        self.assertEqual(
            {
                "circle": "DIXY",
                "genre": ["萌え", "学生"],
                "sample_images": [
                    "//img.dlsite.jp/RJ01190426_img_smp1.jpg",
                    "//img.dlsite.jp/RJ01190426_img_smp2.jpg",
                ],
            },
            product_api_details(AIX_PRODUCT, ["maker", "genre", "sample_images"]),
        )

    def test_non_circle_maker_maps_to_brand(self):
        product = {**AIX_PRODUCT, "maker_id": "VG01000001", "maker_name": "Brand"}

        self.assertEqual({"brand": "Brand"}, product_api_details(product, ["maker"]))

    def test_only_missing_fields_are_mapped(self):
        self.assertEqual(
            {"genre": ["萌え", "学生"]},
            product_api_details(AIX_PRODUCT, ["genre"]),
        )

    def test_empty_product_values_are_not_mapped(self):
        product = {"maker_id": "RG01003097", "genres": [], "image_samples": None}

        self.assertEqual(
            {},
            product_api_details(product, ["maker", "genre", "sample_images"]),
        )

    def test_missing_fields_are_filled_from_product_api(self):
        api = FakeAPI([AIX_PRODUCT])

        work = asyncio.run(fill_from_product_api(api, self.aix_work()))

        self.assertEqual("DIXY", work.circle)
        self.assertEqual(["萌え", "学生"], work.genre)
        self.assertEqual(2, len(work.sample_images))
        self.assertEqual({"workno": "RJ01190426"}, api.requests[0][1])

    def test_existing_page_values_are_kept(self):
        api = FakeAPI([AIX_PRODUCT])
        original = self.aix_work(circle="From page", genre=["Page genre"])

        work = asyncio.run(fill_from_product_api(api, original))

        self.assertEqual("From page", work.circle)
        self.assertEqual(["Page genre"], work.genre)
        self.assertEqual(2, len(work.sample_images))

    def test_complete_work_does_not_call_product_api(self):
        api = FakeAPI([AIX_PRODUCT])
        original = self.aix_work(
            circle="DIXY",
            genre=["萌え"],
            sample_images=["//img.dlsite.jp/smp1.jpg"],
        )

        self.assertIs(original, asyncio.run(fill_from_product_api(api, original)))
        self.assertEqual([], api.requests)

    def test_unexpected_product_api_payload_keeps_work(self):
        original = self.aix_work()

        for payload in ([], {}, None):
            with self.subTest(payload=payload):
                self.assertIs(
                    original,
                    asyncio.run(fill_from_product_api(FakeAPI(payload), original)),
                )


if __name__ == "__main__":
    unittest.main()
