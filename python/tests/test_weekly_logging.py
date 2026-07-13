import logging
import shutil
import sys
import unittest
import uuid
from contextlib import redirect_stderr
from datetime import datetime, timezone
from io import StringIO
from pathlib import Path
from unittest.mock import patch

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

from weekly_logging import WeeklyFileHandler, normalize_retention_days, weekly_log_path


class WeeklyLoggingTest(unittest.TestCase):
    def setUp(self):
        testing_directory = (
            Path(__file__).resolve().parents[2] / "storage/framework/testing"
        )
        testing_directory.mkdir(parents=True, exist_ok=True)
        self.directory = testing_directory / f"python-weekly-logs-{uuid.uuid4().hex}"
        self.directory.mkdir()
        self.addCleanup(shutil.rmtree, self.directory)

    def test_weekly_log_path_uses_the_utc_monday_date(self):
        log_path = weekly_log_path(
            Path("logs"),
            "DLSiteScraper",
            datetime(2026, 7, 19, 23, 59, 59, tzinfo=timezone.utc),
        )

        self.assertEqual(
            Path("logs/DLSiteScraper-2026-07-13.log"),
            log_path,
        )

    def test_invalid_retention_values_fall_back_to_ninety_days(self):
        for value in (None, "", "invalid", 0, "0", -1, "-1"):
            with self.subTest(value=value):
                self.assertEqual(90, normalize_retention_days(value))

        self.assertEqual(45, normalize_retention_days("45"))

    def test_handler_constructor_does_not_expose_a_test_clock(self):
        with self.assertRaises(TypeError):
            WeeklyFileHandler(
                self.directory,
                "DLSiteScraper",
                retention_days=90,
                now=datetime(2026, 7, 13, tzinfo=timezone.utc),
            )

    def test_handler_appends_within_a_week_and_switches_on_monday(self):
        handler = WeeklyFileHandler(
            self.directory,
            "DLSiteScraper",
            retention_days=90,
        )

        handler.handle(self.record("2026-07-13 00:00:00", "Monday entry"))
        handler.handle(self.record("2026-07-19 23:59:59", "Sunday entry"))
        handler.handle(self.record("2026-07-20 00:00:00", "Next Monday entry"))
        handler.close()

        first_week = Path(self.directory, "DLSiteScraper-2026-07-13.log").read_text(
            encoding="utf-8"
        )
        second_week = Path(self.directory, "DLSiteScraper-2026-07-20.log").read_text(
            encoding="utf-8"
        )

        self.assertIn("Monday entry", first_week)
        self.assertIn("Sunday entry", first_week)
        self.assertNotIn("Next Monday entry", first_week)
        self.assertIn("Next Monday entry", second_week)

    def test_handler_prunes_only_expired_matching_weekly_archives(self):
        for filename in (
            "DLSiteScraper-2026-04-06.log",
            "DLSiteScraper-2026-04-13.log",
            "DLSiteScraper-2026-04-07.log",
            "DLSiteScraper-2026-07-13.log",
            "DLSiteScraper-2026-07-20.log",
            "DLSiteScraper-invalid.log",
            "DLSiteScraper.log",
            "laravel-2026-04-06.log",
        ):
            Path(self.directory, filename).write_text(filename, encoding="utf-8")

        handler = WeeklyFileHandler(
            self.directory,
            "DLSiteScraper",
            retention_days=90,
        )
        handler.handle(self.record("2026-07-13 12:00:00", "Cleanup entry"))
        handler.close()

        self.assertFalse(Path(self.directory, "DLSiteScraper-2026-04-06.log").exists())
        self.assertTrue(Path(self.directory, "DLSiteScraper-2026-04-13.log").exists())
        self.assertTrue(Path(self.directory, "DLSiteScraper-2026-04-07.log").exists())
        self.assertTrue(Path(self.directory, "DLSiteScraper-2026-07-13.log").exists())
        self.assertTrue(Path(self.directory, "DLSiteScraper-2026-07-20.log").exists())
        self.assertTrue(Path(self.directory, "DLSiteScraper-invalid.log").exists())
        self.assertTrue(Path(self.directory, "DLSiteScraper.log").exists())
        self.assertTrue(Path(self.directory, "laravel-2026-04-06.log").exists())

    def test_handler_prunes_on_the_first_write_after_expiry_in_the_same_week(self):
        archive = Path(self.directory, "DLSiteScraper-2026-04-13.log")
        archive.write_text("Archive pending expiry", encoding="utf-8")
        handler = WeeklyFileHandler(
            self.directory,
            "DLSiteScraper",
            retention_days=90,
        )

        handler.handle(self.record("2026-07-18 23:59:59", "Before expiry"))
        self.assertTrue(archive.exists())

        handler.handle(self.record("2026-07-19 00:00:00", "At expiry"))
        handler.close()

        self.assertFalse(archive.exists())
        self.assertIn(
            "At expiry",
            Path(self.directory, "DLSiteScraper-2026-07-13.log").read_text(
                encoding="utf-8"
            ),
        )

    def test_archive_removed_by_another_process_is_not_reported_as_a_failure(self):
        archive = Path(self.directory, "DLSiteScraper-2026-04-06.log")
        archive.write_text("Expired archive", encoding="utf-8")
        stderr = StringIO()
        handler = WeeklyFileHandler(
            self.directory,
            "DLSiteScraper",
            retention_days=90,
        )

        with patch(
            "weekly_logging.Path.unlink",
            side_effect=FileNotFoundError("already removed"),
        ):
            with redirect_stderr(stderr):
                handler.handle(
                    self.record("2026-07-13 12:00:00", "Concurrent cleanup write")
                )

        handler.close()

        self.assertEqual("", stderr.getvalue())
        self.assertIn(
            "Concurrent cleanup write",
            Path(self.directory, "DLSiteScraper-2026-07-13.log").read_text(
                encoding="utf-8"
            ),
        )

    def test_cleanup_failure_reports_to_stderr_without_blocking_the_log_write(self):
        expired_archive = Path(self.directory, "DLSiteScraper-2026-04-06.log")
        expired_archive.write_text("Expired archive", encoding="utf-8")
        stderr = StringIO()
        handler = WeeklyFileHandler(
            self.directory,
            "DLSiteScraper",
            retention_days=90,
        )

        with patch("weekly_logging.Path.unlink", side_effect=PermissionError("denied")):
            with redirect_stderr(stderr):
                handler.handle(
                    self.record("2026-07-13 12:00:00", "Write survives cleanup")
                )

        handler.close()

        self.assertTrue(expired_archive.exists())
        self.assertIn("Unable to delete expired weekly log archive", stderr.getvalue())
        self.assertIn(
            "Write survives cleanup",
            Path(self.directory, "DLSiteScraper-2026-07-13.log").read_text(
                encoding="utf-8"
            ),
        )

    def record(self, timestamp: str, message: str) -> logging.LogRecord:
        moment = datetime.strptime(timestamp, "%Y-%m-%d %H:%M:%S").replace(
            tzinfo=timezone.utc
        )
        record = logging.LogRecord(
            name="testing",
            level=logging.INFO,
            pathname=__file__,
            lineno=1,
            msg=message,
            args=(),
            exc_info=None,
        )
        record.created = moment.timestamp()

        return record


if __name__ == "__main__":
    unittest.main()
