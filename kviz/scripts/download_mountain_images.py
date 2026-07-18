#!/usr/bin/env python3
"""Download mountain peak images from Wikimedia/Wikipedia for mountain_peaks.csv entries."""

from __future__ import annotations

import argparse
import csv
import re
import time
import unicodedata
from pathlib import Path
from urllib.error import HTTPError, URLError
from urllib.request import Request, urlopen
from urllib.parse import urlencode

USER_AGENT = "KvizMountainImageDownloader/1.0 (https://github.com/local/kviz; educational quiz project)"
THUMB_SIZE = 800
RATE_LIMIT_SEC = 1.0

FIELDNAMES = ["Name", "Elevation (m)", "Country", "Range", "Fun Fact", "Image"]

TRANSLIT = str.maketrans(
    {
        "á": "a", "é": "e", "í": "i", "ó": "o", "ö": "o", "ő": "o",
        "ú": "u", "ü": "u", "ű": "u",
        "Á": "a", "É": "e", "Í": "i", "Ó": "o", "Ö": "o", "Ő": "o",
        "Ú": "u", "Ü": "u", "Ű": "u",
    }
)


def slugify(name: str) -> str:
    text = name.translate(TRANSLIT).lower()
    text = unicodedata.normalize("NFKD", text)
    text = text.encode("ascii", "ignore").decode("ascii")
    text = re.sub(r"[^a-z0-9]+", "-", text)
    return text.strip("-") or "mountain"


def api_get(base_url: str, params: dict) -> dict:
    import json as json_mod
    query = urlencode(params, doseq=True)
    url = f"{base_url}?{query}"
    request = Request(url, headers={"User-Agent": USER_AGENT})
    with urlopen(request, timeout=30) as response:
        return json_mod.loads(response.read().decode("utf-8"))


def page_image(base_url: str, title: str) -> dict | None:
    data = api_get(
        base_url,
        {
            "action": "query",
            "format": "json",
            "redirects": 1,
            "prop": "pageimages|info",
            "inprop": "url",
            "piprop": "thumbnail|name",
            "pithumbsize": THUMB_SIZE,
            "titles": title,
        },
    )
    pages = data.get("query", {}).get("pages", {})
    for page in pages.values():
        if int(page.get("ns", -1)) < 0:
            continue
        thumb = page.get("thumbnail", {}).get("source")
        if thumb:
            return {
                "url": thumb,
                "page_url": page.get("fullurl"),
                "file_name": page.get("pageimage"),
                "source": base_url,
                "method": "pageimages",
                "title": page.get("title"),
            }
    return None


def wiki_search_then_image(base_url: str, query: str) -> dict | None:
    data = api_get(
        base_url,
        {
            "action": "query",
            "format": "json",
            "list": "search",
            "srlimit": 5,
            "srsearch": query,
        },
    )
    for item in data.get("query", {}).get("search", []):
        result = page_image(base_url, item["title"])
        if result:
            result["method"] = "search+pageimages"
            result["search_query"] = query
            return result
    return None


def commons_image(query: str) -> dict | None:
    data = api_get(
        "https://commons.wikimedia.org/w/api.php",
        {
            "action": "query",
            "format": "json",
            "generator": "search",
            "gsrsearch": f"{query} mountain peak",
            "gsrnamespace": 6,
            "gsrlimit": 8,
            "prop": "imageinfo|info",
            "inprop": "url",
            "iiprop": "url|extmetadata",
            "iiurlwidth": THUMB_SIZE,
        },
    )
    pages = data.get("query", {}).get("pages", {})
    for page in sorted(pages.values(), key=lambda p: p.get("index", 0)):
        info = (page.get("imageinfo") or [{}])[0]
        url = info.get("thumburl") or info.get("url")
        if not url:
            continue
        ext = info.get("extmetadata") or {}
        return {
            "url": url,
            "page_url": page.get("fullurl"),
            "file_name": page.get("title", "").removeprefix("File:"),
            "source": "https://commons.wikimedia.org/w/api.php",
            "method": "commons-search",
            "title": page.get("title"),
            "license": (ext.get("LicenseShortName") or {}).get("value"),
            "artist": (ext.get("Artist") or {}).get("value"),
        }
    return None


def resolve_search_terms(mountain: dict) -> list[str]:
    name = mountain["Name"]
    terms = [name]
    country = (mountain.get("Country") or "").strip()
    if country:
        terms.append(f"{name} {country}")
    rng = (mountain.get("Range") or "").strip()
    if rng:
        terms.append(f"{name} {rng}")
    return terms


def find_image(mountain: dict) -> tuple[dict | None, list[str]]:
    tried: list[str] = []
    terms = resolve_search_terms(mountain)
    name = mountain["Name"]

    tried.append(f"en.wikipedia pageimages:{name}")
    result = page_image("https://en.wikipedia.org/w/api.php", name)
    if result:
        return result, tried

    tried.append(f"hu.wikipedia pageimages:{name}")
    result = page_image("https://hu.wikipedia.org/w/api.php", name)
    if result:
        return result, tried

    for term in terms:
        tried.append(f"en.wikipedia search:{term}")
        result = wiki_search_then_image("https://en.wikipedia.org/w/api.php", term)
        if result:
            return result, tried

    tried.append(f"hu.wikipedia search:{name}")
    result = wiki_search_then_image("https://hu.wikipedia.org/w/api.php", name)
    if result:
        return result, tried

    for term in terms:
        tried.append(f"commons search:{term}")
        result = commons_image(term)
        if result:
            return result, tried

    return None, tried


def download_file(url: str, dest: Path) -> None:
    request = Request(url, headers={"User-Agent": USER_AGENT})
    with urlopen(request, timeout=60) as response:
        dest.write_bytes(response.read())


def extension_from_url(url: str) -> str:
    path = url.split("?", 1)[0].lower()
    for ext in (".jpg", ".jpeg", ".png", ".webp", ".gif"):
        if path.endswith(ext):
            return ".jpg" if ext == ".jpeg" else ext
    return ".jpg"


def unique_image_path(images_dir: Path, slug: str, url: str, used: set[str]) -> Path:
    ext = extension_from_url(url)
    candidate = f"{slug}{ext}"
    if candidate not in used and not (images_dir / candidate).exists():
        used.add(candidate)
        return images_dir / candidate

    index = 2
    while True:
        candidate = f"{slug}-{index}{ext}"
        if candidate not in used and not (images_dir / candidate).exists():
            used.add(candidate)
            return images_dir / candidate
        index += 1


def main() -> int:
    parser = argparse.ArgumentParser(description="Download mountain images from Wikimedia.")
    parser.add_argument("--dry-run", action="store_true", help="Resolve URLs without downloading.")
    parser.add_argument("--force", action="store_true", help="Re-download even if image exists.")
    parser.add_argument("--limit", type=int, default=0, help="Process only the first N mountains.")
    args = parser.parse_args()

    root = Path(__file__).resolve().parent.parent
    csv_path = root / "mountain_peaks.csv"
    images_dir = root / "images" / "mountains"
    report_path = root / "download_mountain_report.json"
    attribution_path = root / "mountain_image_attribution.json"

    with csv_path.open("r", encoding="utf-8-sig", newline="") as fh:
        reader = csv.DictReader(fh)
        all_mountains = list(reader)

    mountains = all_mountains[: args.limit] if args.limit > 0 else all_mountains

    images_dir.mkdir(parents=True, exist_ok=True)

    report: list[dict] = []
    attribution: dict[str, dict] = {}
    used_names: set[str] = set()
    updated = 0
    skipped = 0
    missing = 0

    for mountain in mountains:
        name = mountain["Name"]
        slug = slugify(name)
        existing = (mountain.get("Image") or "").strip()
        existing_path = root / existing if existing else None

        if (
            not args.force
            and existing_path
            and existing_path.is_file()
            and not args.dry_run
        ):
            report.append({"name": name, "status": "skipped", "image": existing, "search_terms_tried": []})
            skipped += 1
            continue

        image_info, tried = find_image(mountain)
        time.sleep(RATE_LIMIT_SEC)

        if not image_info:
            report.append({"name": name, "status": "missing", "source_url": None, "search_terms_tried": tried})
            missing += 1
            continue

        rel_path = f"images/mountains/{unique_image_path(images_dir, slug, image_info['url'], used_names).name}"

        if args.dry_run:
            report.append({
                "name": name, "status": "dry-run",
                "source_url": image_info.get("page_url") or image_info["url"],
                "image_url": image_info["url"], "image": rel_path, "search_terms_tried": tried,
            })
            updated += 1
            continue

        dest = root / rel_path
        download_file(image_info["url"], dest)
        mountain["Image"] = rel_path.replace("\\", "/")
        attribution[name] = {
            "image": mountain["Image"],
            "source_url": image_info.get("page_url") or image_info["url"],
            "image_url": image_info["url"],
            "file_name": image_info.get("file_name"),
            "license": image_info.get("license"),
            "artist": image_info.get("artist"),
            "method": image_info.get("method"),
            "title": image_info.get("title"),
        }
        report.append({
            "name": name, "status": "ok",
            "source_url": image_info.get("page_url") or image_info["url"],
            "image": mountain["Image"], "search_terms_tried": tried,
        })
        updated += 1
        print(f"[ok] {name} -> {mountain['Image']}")

    if not args.dry_run:
        if args.limit > 0:
            by_name = {m["Name"]: m for m in mountains}
            for m in all_mountains:
                if m["Name"] in by_name and by_name[m["Name"]].get("Image"):
                    m["Image"] = by_name[m["Name"]]["Image"]

        for m in all_mountains:
            m.setdefault("Image", "")

        with csv_path.open("w", encoding="utf-8", newline="") as fh:
            writer = csv.DictWriter(fh, fieldnames=FIELDNAMES)
            writer.writeheader()
            writer.writerows(all_mountains)

        report_path.write_text(
            __import__("json").dumps(report, ensure_ascii=False, indent=2) + "\n", encoding="utf-8"
        )

        if attribution:
            existing_attr = {}
            if attribution_path.exists():
                existing_attr = __import__("json").loads(attribution_path.read_text(encoding="utf-8"))
            existing_attr.update(attribution)
            attribution_path.write_text(
                __import__("json").dumps(existing_attr, ensure_ascii=False, indent=2) + "\n", encoding="utf-8"
            )
    else:
        report_path.write_text(
            __import__("json").dumps(report, ensure_ascii=False, indent=2) + "\n", encoding="utf-8"
        )

    print(f"Done. ok/dry-run={updated}, skipped={skipped}, missing={missing}")
    return 0


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except (HTTPError, URLError, TimeoutError) as exc:
        print(f"Network error: {exc}")
        raise SystemExit(1)
