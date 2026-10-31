import sys
import unittest
from pathlib import Path
from types import SimpleNamespace
from unittest.mock import AsyncMock, mock_open, patch

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

from dlsite_async.work import (
    AgeCategory,
    BookType,
    LanguageEdition,
    TranslationInfo,
    WorkOption,
    WorkType,
)
from DLSiteScraper import product_format_codes, run, to_serializable


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


class DLSiteScraperOutputTest(unittest.TestCase):
    def test_language_editions_keep_original_work_reference(self):
        work = SimpleNamespace(
            language_editions=[
                LanguageEdition(
                    workno="RJ01463781",
                    edition_id=43711,
                    lang="JPN",
                    edition_type="language",
                    label="日本語",
                    display_order=1,
                )
            ]
        )
        serialized = to_serializable(work)
        self.assertEqual(
            "RJ01463781", serialized["language_editions"][0]["workno"]
        )
        self.assertEqual(1, serialized["language_editions"][0]["display_order"])
        self.assertEqual("JPN", serialized["language_editions"][0]["lang"])

    def test_translation_info_keeps_original_work_reference(self):
        work = SimpleNamespace(
            translation_info=TranslationInfo(
                original_workno="RJ01647180", is_original=False
            )
        )
        serialized = to_serializable(work)
        self.assertEqual(
            "RJ01647180", serialized["translation_info"]["original_workno"]
        )
        self.assertFalse(serialized["translation_info"]["is_original"])

    def test_output_preserves_serialized_fields_without_fetch_metadata(self):
        work = SimpleNamespace(
            product_id="VJ015443", scenario=["天宮りつ"], work_type=WorkType.VOICE_ASMR
        )
        expected = to_serializable(work) | {
            "product_format": product_format_codes(work)
        }
        arguments = SimpleNamespace(
            work_id="VJ015443", json_output="unused/work.json", image_output=None
        )
        with (
            patch(
                "DLSiteScraper.fetch_work_data", AsyncMock(return_value=(work, work))
            ),
            patch("DLSiteScraper.Path.mkdir"),
            patch("builtins.open", mock_open()),
            patch("DLSiteScraper.json.dump") as dump,
        ):
            manifest = run(arguments)
        document = dump.call_args.args[0]
        self.assertEqual({"japanese", "english"}, set(document))
        for locale in ("japanese", "english"):
            self.assertEqual(expected, document[locale])
        self.assertEqual(
            {"product_id", "json_path", "downloaded_images", "failed_images"},
            set(manifest),
        )


if __name__ == "__main__":
    unittest.main()
