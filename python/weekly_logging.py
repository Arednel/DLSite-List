import logging
import os
import re
import sys
from datetime import datetime, timedelta, timezone
from pathlib import Path

DEFAULT_RETENTION_DAYS = 90


def normalize_retention_days(value) -> int:
    try:
        retention_days = int(value)
    except (TypeError, ValueError):
        return DEFAULT_RETENTION_DAYS

    return retention_days if retention_days > 0 else DEFAULT_RETENTION_DAYS


def week_start_utc(moment: datetime) -> datetime:
    if moment.tzinfo is None:
        moment = moment.replace(tzinfo=timezone.utc)

    utc_moment = moment.astimezone(timezone.utc)

    return (utc_moment - timedelta(days=utc_moment.weekday())).replace(
        hour=0,
        minute=0,
        second=0,
        microsecond=0,
    )


def weekly_log_path(directory: Path, stem: str, moment: datetime) -> Path:
    week_start = week_start_utc(moment)

    return directory / f"{stem}-{week_start:%Y-%m-%d}.log"


class WeeklyFileHandler(logging.FileHandler):
    def __init__(
        self,
        directory: Path,
        stem: str,
        retention_days=DEFAULT_RETENTION_DAYS,
    ):
        self.directory = Path(directory)
        self.directory.mkdir(parents=True, exist_ok=True)
        self.stem = stem
        self.retention_days = normalize_retention_days(retention_days)

        super().__init__(
            weekly_log_path(
                self.directory,
                self.stem,
                datetime.now(timezone.utc),
            ),
            mode="a",
            encoding="utf-8",
            delay=True,
        )

    def emit(self, record: logging.LogRecord):
        moment = datetime.fromtimestamp(record.created, tz=timezone.utc)
        target = os.path.abspath(weekly_log_path(self.directory, self.stem, moment))

        if self.baseFilename != target:
            if self.stream:
                self.stream.flush()
                self.stream.close()
                self.stream = None

            self.baseFilename = target

        self._prune_expired_archives(moment)

        super().emit(record)

    def _prune_expired_archives(self, moment: datetime):
        archive_pattern = re.compile(
            rf"^{re.escape(self.stem)}-(\d{{4}}-\d{{2}}-\d{{2}})\.log$"
        )

        for archive in self.directory.glob(f"{self.stem}-*.log"):
            match = archive_pattern.fullmatch(archive.name)

            if not match:
                continue

            try:
                week_start = datetime.strptime(match.group(1), "%Y-%m-%d").replace(
                    tzinfo=timezone.utc
                )
            except ValueError:
                continue

            if week_start.weekday() != 0:
                continue

            expires_at = week_start + timedelta(days=7 + self.retention_days)

            if expires_at > moment:
                continue

            try:
                archive.unlink()
            except FileNotFoundError:
                continue
            except OSError as error:
                print(
                    f"Unable to delete expired weekly log archive {archive}: {error}",
                    file=sys.stderr,
                )
