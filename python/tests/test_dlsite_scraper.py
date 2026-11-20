import asyncio
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
from DLSiteScraper import (
    fetch_work_data,
    map_known_error,
    parse_locale_config,
    product_format_codes,
    run,
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


class DLSiteScraperErrorCodeTest(unittest.TestCase):
    def test_known_fetch_errors_return_semantic_error_codes(self):
        cases = [
            (
                "Failed to get product info for RJ123456",
                ("dlsite.work.geo_blocked", 2),
            ),
            ("Not Found", ("dlsite.work.deleted_or_missing", 2)),
            ("Bad Request", ("dlsite.work.missing", 2)),
        ]

        for message, expected in cases:
            with self.subTest(message=message):
                self.assertEqual(expected, map_known_error(message, "RJ123456"))

        self.assertIsNone(map_known_error("Unexpected upstream error", "RJ123456"))


class DLSiteScraperLanguageFetchTest(unittest.TestCase):
    def test_fetch_work_data_executes_supplied_locale_map_in_order_without_whitelisting(
        self,
    ):
        with patch(
            "DLSiteScraper.dlsite_for_locale",
            AsyncMock(side_effect=["ja-work", "future-work"]),
        ) as fetch:
            works = asyncio.run(
                fetch_work_data(
                    "RJ123456",
                    {"ja": None, "future": "future_API"},
                )
            )

        self.assertEqual({"ja": "ja-work", "future": "future-work"}, works)
        self.assertEqual(
            [("RJ123456", None), ("RJ123456", "future_API")],
            [call.args for call in fetch.await_args_list],
        )

    def test_fetch_work_data_reports_output_and_api_locale_when_fetch_fails(self):
        with patch(
            "DLSiteScraper.dlsite_for_locale",
            AsyncMock(side_effect=RuntimeError("Bad Request")),
        ):
            with self.assertRaisesRegex(
                RuntimeError,
                "Failed to fetch locale 'future' using DLsite API locale 'future_API': Bad Request",
            ) as raised:
                asyncio.run(
                    fetch_work_data(
                        "RJ123456",
                        {"future": "future_API"},
                    )
                )

        self.assertEqual(
            ("dlsite.work.missing", 2),
            map_known_error(str(raised.exception), "RJ123456"),
        )


class DLSiteScraperLocaleConfigTest(unittest.TestCase):
    def test_locale_config_returns_laravel_supplied_execution_plan(self):
        self.assertEqual(
            ("future", {"future": "future_API", "secondary": None}),
            parse_locale_config(
                '{"canonical":"future","locales":{"future":"future_API","secondary":null}}'
            ),
        )

    def test_locale_config_only_validates_transport_shape(self):
        self.assertEqual(
            ("", {"": 123}),
            parse_locale_config('{"canonical":"","locales":{"":123}}'),
        )

        cases = [
            ("not-json", "Invalid locale config JSON"),
            ("[]", "Locale config must be an object"),
            (
                '{"canonical":123,"locales":{"ja":null}}',
                "canonical key must be a string",
            ),
            ('{"canonical":"ja","locales":[]}', "locales must be an object"),
            (
                '{"canonical":"ja","locales":{"en":"en_US"}}',
                "canonical key must exist in locales",
            ),
        ]

        for raw_config, message in cases:
            with self.subTest(raw_config=raw_config):
                with self.assertRaisesRegex(ValueError, message):
                    parse_locale_config(raw_config)


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
        self.assertEqual("RJ01463781", serialized["language_editions"][0]["workno"])
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
        canonical_work = SimpleNamespace(
            product_id="VJ015443", scenario=["天宮りつ"], work_type=WorkType.VOICE_ASMR
        )
        secondary_work = SimpleNamespace(
            product_id="RJ999999", scenario=["Secondary"], work_type=WorkType.MANGA
        )
        expected_canonical = to_serializable(canonical_work) | {
            "product_format": product_format_codes(canonical_work)
        }
        expected_secondary = to_serializable(secondary_work) | {
            "product_format": product_format_codes(secondary_work)
        }
        arguments = SimpleNamespace(
            work_id="VJ015443",
            json_output="unused/work.json",
            image_output=None,
            locale_config='{"canonical":"future","locales":{"future":"future_API","en":"en_US"}}',
        )
        with (
            patch(
                "DLSiteScraper.fetch_work_data",
                AsyncMock(
                    return_value={"future": canonical_work, "en": secondary_work}
                ),
            ) as fetch,
            patch("DLSiteScraper.Path.mkdir"),
            patch("builtins.open", mock_open()),
            patch("DLSiteScraper.json.dump") as dump,
        ):
            manifest = run(arguments)
        document = dump.call_args.args[0]
        self.assertEqual({"locales"}, set(document))
        self.assertEqual({"future", "en"}, set(document["locales"]))
        self.assertEqual(expected_canonical, document["locales"]["future"])
        self.assertEqual(expected_secondary, document["locales"]["en"])
        fetch.assert_awaited_once_with(
            "VJ015443",
            {"future": "future_API", "en": "en_US"},
        )
        self.assertEqual(
            {"product_id", "json_path", "downloaded_images", "failed_images"},
            set(manifest),
        )
        self.assertEqual("VJ015443", manifest["product_id"])


if __name__ == "__main__":
    unittest.main()
