#!/usr/bin/env python3
"""Validate an initial credential-free Finprov release directory or ZIP, not a connected frontend update."""
from __future__ import annotations

import argparse
import re
import stat
import sys
import zipfile
from pathlib import Path, PurePosixPath

REQUIRED = {
    ".htaccess",
    "index.html",
    "site-config.js",
    "tracking.js",
    "api/lead.php",
    "api/config.php",
    "api/config.example.php",
    "api/.htaccess",
    "api/storage/.htaccess",
    "assets/finprov-logo.png",
    "assets/favicon.png",
}
FORBIDDEN_PARTS = {".git", "node_modules", "__MACOSX", "__pycache__", ".DS_Store"}
FORBIDDEN_NAMES = {".env", "failed.jsonl", "dry-run.jsonl", "captured.jsonl"}
SECRET_PATTERNS = [
    re.compile(r"-----BEGIN (?:OPENSSH |RSA |EC )?PRIVATE KEY-----"),
    re.compile(r"(?:gh[pousr]_|github_pat_)[A-Za-z0-9_]{20,}"),
    re.compile(r"['\"]lsq_access_key['\"]\s*=>\s*['\"](?!REPLACE_)[^'\"]{8,}['\"]", re.I),
    re.compile(r"['\"]lsq_secret_key['\"]\s*=>\s*['\"](?!REPLACE_)[^'\"]{8,}['\"]", re.I),
]


def normalize(name: str) -> str:
    value = name.replace("\\", "/")
    while value.startswith("./"):
        value = value[2:]
    return value.rstrip("/")


def unsafe_path(name: str) -> bool:
    normalized = name.replace("\\", "/")
    path = PurePosixPath(normalized)
    return (
        normalized.startswith("/")
        or bool(re.match(r"^[A-Za-z]:/", normalized))
        or ".." in path.parts
        or "" in path.parts
    )


def validate_names(names: set[str]) -> list[str]:
    issues = [f"missing required file: {name}" for name in sorted(REQUIRED - names)]
    for name in sorted(names):
        if unsafe_path(name):
            issues.append(f"unsafe release path: {name}")
        if set(PurePosixPath(name).parts) & FORBIDDEN_PARTS or PurePosixPath(name).name in FORBIDDEN_NAMES or PurePosixPath(name).name.startswith(".env."):
            issues.append(f"forbidden release content: {name}")
    top = {name.split("/", 1)[0] for name in names if name}
    if "index.html" not in top and len(top) == 1:
        issues.append("ZIP appears to contain a redundant outer directory")
    return issues


def inspect_text(name: str, data: bytes, template: bool) -> list[str]:
    if len(data) > 2_000_000:
        return []
    text, issues = data.decode("utf-8", errors="ignore"), []
    for pattern in SECRET_PATTERNS:
        if pattern.search(text):
            issues.append(f"possible live LeadSquared credential in {name}")
    if name == "api/config.php":
        if not re.search(r"^\s*['\"]dry_run['\"]\s*=>\s*true\s*,?\s*$", text, re.I | re.M):
            issues.append("api/config.php must ship with dry_run => true")
        if not re.search(r"^\s*['\"]lsq_access_key['\"]\s*=>\s*['\"]REPLACE_ON_SERVER['\"]", text, re.M):
            issues.append("api/config.php must contain the access-key placeholder")
        if not re.search(r"^\s*['\"]lsq_secret_key['\"]\s*=>\s*['\"]REPLACE_ON_SERVER['\"]", text, re.M):
            issues.append("api/config.php must contain the secret-key placeholder")
    if name == "index.html":
        if "assets/finprov-logo.png" not in text:
            issues.append("index.html must reference local assets/finprov-logo.png")
        if "assets/favicon.png" not in text:
            issues.append("index.html must reference local assets/favicon.png")
        if re.search(r"https?://finprov\.com/wp-content/uploads/", text, re.I):
            issues.append("index.html must not hotlink Finprov WordPress assets")
        if not template and re.search(r"<meta[^>]+name=['\"]robots['\"][^>]+noindex", text, re.I):
            issues.append("production index.html must not contain a noindex robots directive")
    if not template and name in {"index.html", "site-config.js"} and "REPLACE_" in text:
        issues.append(f"unresolved release placeholder in {name}")
    return issues


def inspect_binary(name: str, data: bytes) -> list[str]:
    if name in {"assets/finprov-logo.png", "assets/favicon.png"} and not data.startswith(b"\x89PNG\r\n\x1a\n"):
        return [f"brand asset is not a valid PNG: {name}"]
    return []


def validate_directory(root: Path, template: bool) -> list[str]:
    files = {normalize(str(path.relative_to(root))) for path in root.rglob("*") if path.is_file()}
    issues = validate_names(files)
    for name in files:
        path = root / name
        if path.is_symlink():
            issues.append(f"symbolic links are not allowed: {name}")
            continue
        data = path.read_bytes()
        issues.extend(inspect_text(name, data, template))
        issues.extend(inspect_binary(name, data))
    return issues


def validate_zip(path: Path, template: bool) -> list[str]:
    try:
        with zipfile.ZipFile(path) as archive:
            raw_files = [info for info in archive.infolist() if not info.is_dir()]
            normalized = [normalize(info.filename) for info in raw_files]
            issues: list[str] = []
            if len(normalized) != len(set(normalized)):
                issues.append("ZIP contains duplicate or colliding normalized paths")
            names = set(normalized)
            issues.extend(validate_names(names))
            for info, name in zip(raw_files, normalized):
                mode = (info.external_attr >> 16) & 0xFFFF
                if stat.S_ISLNK(mode):
                    issues.append(f"symbolic links are not allowed: {name}")
                    continue
                data = archive.read(info)
                issues.extend(inspect_text(name, data, template))
                issues.extend(inspect_binary(name, data))
            return issues
    except zipfile.BadZipFile:
        return ["file is not a valid ZIP archive"]


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("target", type=Path)
    parser.add_argument(
        "--template",
        action="store_true",
        help="allow starter-template placeholders and noindex while validating structure",
    )
    args = parser.parse_args()
    if not args.target.exists():
        print(f"FAIL: not found: {args.target}")
        return 2
    issues = (
        validate_directory(args.target, args.template)
        if args.target.is_dir()
        else validate_zip(args.target, args.template)
    )
    if issues:
        print("RELEASE VALIDATION FAILED")
        for issue in issues:
            print(f"- {issue}")
        return 1
    print("RELEASE VALIDATION PASSED")
    return 0


if __name__ == "__main__":
    sys.exit(main())
