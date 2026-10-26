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
from DLSiteScraper import (
    download_image,
    fetch_images,
    normalize_image_url,
    validate_image_redirect,
)


def png_bytes(width=4, height=4):
    buffer = io.BytesIO()
    Image.new("RGB", (width, height), (255, 0, 0)).save(buffer, format="PNG")
    return buffer.getvalue()


class FakeResponse:
    def __init__(
        self,
        body=b"",
        status_code=200,
        *,
        url="https://img.dlsite.jp/image.jpg",
        headers=None,
    ):
        self.body = body
        self.status_code = status_code
        self.url = url
        self.headers = headers or {}
        self.closed = False

    def __enter__(self):
        return self

    def __exit__(self, exc_type, exc_value, traceback):
        self.close()

    def close(self):
        self.closed = True

    @property
    def is_redirect(self):
        return self.status_code in {301, 302, 303, 307, 308} and "Location" in self.headers

    def raise_for_status(self):
        if self.status_code >= 400:
            raise requests.HTTPError(f"HTTP {self.status_code}")

    def iter_content(self, chunk_size):
        for start in range(0, len(self.body), chunk_size):
            yield self.body[start : start + chunk_size]


class ImageHttpsTest(unittest.TestCase):
    def setUp(self):
        self.directory = tempfile.TemporaryDirectory()
        self.target = Path(self.directory.name) / "cover.jpg"

    def tearDown(self):
        self.directory.cleanup()

    def test_protocol_relative_and_site_relative_urls_are_normalized_to_https(self):
        self.assertEqual(
            "https://img.dlsite.jp/images/cover.jpg",
            normalize_image_url("//img.dlsite.jp/images/cover.jpg"),
        )
        self.assertEqual(
            "https://www.dlsite.com/images/cover.jpg",
            normalize_image_url("/images/cover.jpg"),
        )

    def test_https_urls_are_not_restricted_to_specific_hosts(self):
        self.assertEqual(
            "https://example.com/image.jpg",
            normalize_image_url("https://example.com/image.jpg"),
        )
        self.assertEqual(
            "https://www.comipo.app/image.jpg",
            normalize_image_url("https://www.comipo.app/image.jpg"),
        )

    def test_https_url_without_hostname_is_rejected_with_clear_error(self):
        with self.assertRaisesRegex(ValueError, "Image URL must include a hostname"):
            normalize_image_url("https:///image.jpg")

    def test_http_url_is_rejected_before_request_with_clear_error(self):
        with mock.patch.object(DLSiteScraper.requests, "get") as get:
            with self.assertLogs(level="WARNING") as logs:
                self.assertFalse(download_image("http://example.com/image.jpg", self.target))

        get.assert_not_called()
        self.assertIn("Image URL must use HTTPS", "\n".join(logs.output))
        self.assertFalse(self.target.exists())
        self.assertFalse(self.target.with_name("cover.jpg.part").exists())

    def test_http_redirect_is_rejected_before_following(self):
        response = FakeResponse(
            status_code=302,
            url="https://img.dlsite.jp/image.jpg",
            headers={"Location": "http://cdn.example/image.jpg"},
        )

        with self.assertRaisesRegex(ValueError, "Image URL must use HTTPS"):
            validate_image_redirect(response)

        self.assertTrue(response.closed)

    def test_https_redirect_to_any_host_is_allowed(self):
        response = FakeResponse(
            status_code=302,
            url="https://img.dlsite.jp/image.jpg",
            headers={"Location": "https://cdn.example/image.jpg"},
        )

        self.assertIs(response, validate_image_redirect(response))

    def test_rejected_url_is_reported_as_failed_image(self):
        work = {"work_image": "http://example.com/cover.jpg", "sample_images": []}

        with self.assertLogs(level="WARNING"):
            downloaded, failed = fetch_images(work, Path(self.directory.name))

        self.assertEqual([], downloaded)
        self.assertEqual(["cover.jpg"], failed)

    def test_https_image_download_still_succeeds(self):
        response = FakeResponse(png_bytes())

        with mock.patch.object(DLSiteScraper.requests, "get", return_value=response) as get:
            self.assertTrue(download_image("https://cdn.example/image.png", self.target))

        get.assert_called_once()
        self.assertIn("response", get.call_args.kwargs["hooks"])
        self.assertTrue(self.target.exists())


if __name__ == "__main__":
    unittest.main()
