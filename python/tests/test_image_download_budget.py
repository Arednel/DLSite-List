import io
import sys
import tempfile
import unittest
from pathlib import Path
from unittest import mock

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

import requests
from PIL import Image

import DLSiteScraper
from DLSiteScraper import download_image, limit_sample_images


def png_bytes(width=4, height=4):
    buffer = io.BytesIO()
    Image.new("RGB", (width, height), (255, 0, 0)).save(buffer, format="PNG")
    return buffer.getvalue()


class FakeResponse:
    def __init__(self, body=b"", status_code=200, headers=None):
        self.body = body
        self.status_code = status_code
        self.headers = headers or {}
        self.closed = False

    def raise_for_status(self):
        if self.status_code >= 400:
            raise requests.HTTPError(f"HTTP {self.status_code}")

    def iter_content(self, chunk_size):
        for start in range(0, len(self.body), chunk_size):
            yield self.body[start : start + chunk_size]

    def close(self):
        self.closed = True

    def __enter__(self):
        return self

    def __exit__(self, *exc_info):
        self.close()


class ImageDownloadBudgetTest(unittest.TestCase):
    def setUp(self):
        self.directory = tempfile.TemporaryDirectory()
        self.target = Path(self.directory.name) / "cover.jpg"

    def tearDown(self):
        self.directory.cleanup()

    def assertNothingSaved(self):
        self.assertFalse(self.target.exists())
        self.assertFalse(self.target.with_name("cover.jpg.part").exists())

    def test_image_within_budgets_is_saved(self):
        response = FakeResponse(png_bytes())

        with mock.patch.object(DLSiteScraper.requests, "get", return_value=response):
            self.assertTrue(download_image("https://img.dlsite.jp/a.png", self.target))

        self.assertTrue(self.target.exists())
        self.assertTrue(response.closed)

    def test_declared_oversized_response_is_rejected(self):
        response = FakeResponse(
            png_bytes(),
            headers={"Content-Length": str(DLSiteScraper.MAX_IMAGE_BYTES + 1)},
        )

        with mock.patch.object(DLSiteScraper.requests, "get", return_value=response):
            self.assertFalse(download_image("https://img.dlsite.jp/a.png", self.target))

        self.assertTrue(response.closed)
        self.assertNothingSaved()

    def test_streamed_body_beyond_the_byte_budget_is_rejected(self):
        response = FakeResponse(b"x" * 200_000)

        with (
            mock.patch.object(DLSiteScraper, "MAX_IMAGE_BYTES", 100_000),
            mock.patch.object(DLSiteScraper.requests, "get", return_value=response),
        ):
            self.assertFalse(download_image("https://img.dlsite.jp/a.png", self.target))

        self.assertNothingSaved()

    def test_slow_downloads_are_stopped_at_the_time_budget(self):
        response = FakeResponse(b"x" * 200_000)
        clock = iter([0.0] + [DLSiteScraper.MAX_IMAGE_DOWNLOAD_SECONDS + 1.0] * 10)

        with (
            mock.patch.object(DLSiteScraper.time, "monotonic", side_effect=lambda: next(clock)),
            mock.patch.object(DLSiteScraper.requests, "get", return_value=response),
        ):
            self.assertFalse(download_image("https://img.dlsite.jp/a.png", self.target))

        self.assertNothingSaved()

    def test_images_beyond_the_dimension_budget_are_rejected(self):
        response = FakeResponse(png_bytes(10, 10))

        with (
            mock.patch.object(DLSiteScraper, "MAX_IMAGE_DIMENSION", 5),
            mock.patch.object(DLSiteScraper.requests, "get", return_value=response),
        ):
            self.assertFalse(download_image("https://img.dlsite.jp/a.png", self.target))

        self.assertNothingSaved()

    def test_images_beyond_the_pixel_budget_are_rejected(self):
        response = FakeResponse(png_bytes(10, 10))

        with (
            mock.patch.object(DLSiteScraper, "MAX_IMAGE_PIXELS", 50),
            mock.patch.object(DLSiteScraper.requests, "get", return_value=response),
        ):
            self.assertFalse(download_image("https://img.dlsite.jp/a.png", self.target))

        self.assertNothingSaved()


class SampleImageCountBudgetTest(unittest.TestCase):
    def test_sample_image_metadata_is_truncated_to_the_budget(self):
        work = {
            "sample_images": [
                f"//img.dlsite.jp/sample_{index}.jpg"
                for index in range(DLSiteScraper.MAX_SAMPLE_IMAGES + 10)
            ]
        }

        limited = limit_sample_images(work)

        self.assertEqual(DLSiteScraper.MAX_SAMPLE_IMAGES, len(limited["sample_images"]))
        self.assertEqual("//img.dlsite.jp/sample_0.jpg", limited["sample_images"][0])

    def test_fetch_images_downloads_at_most_the_budgeted_samples(self):
        work = {
            "work_image": "//img.dlsite.jp/main.jpg",
            "sample_images": [
                f"//img.dlsite.jp/sample_{index}.jpg"
                for index in range(DLSiteScraper.MAX_SAMPLE_IMAGES + 10)
            ],
        }

        with tempfile.TemporaryDirectory() as directory, mock.patch.object(
            DLSiteScraper, "download_image", return_value=True
        ) as download:
            downloaded, failed = DLSiteScraper.fetch_images(work, Path(directory))

        self.assertEqual(DLSiteScraper.MAX_SAMPLE_IMAGES + 1, download.call_count)
        self.assertEqual(DLSiteScraper.MAX_SAMPLE_IMAGES + 1, len(downloaded))
        self.assertEqual([], failed)


if __name__ == "__main__":
    unittest.main()
