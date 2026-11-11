"""Build an installable plugin ZIP with a stable woo-complete-the-look/ root."""
from pathlib import Path
from zipfile import ZIP_DEFLATED, ZipFile

root = Path(__file__).resolve().parents[1]
allowed = {
    ".php",
    ".css",
    ".js",
    ".xml",
    ".txt",
    ".md",
    ".png",
    ".jpg",
    ".jpeg",
    ".svg",
    ".webp",
    ".woff",
    ".woff2",
    ".mo",
    ".po",
    ".pot",
}
skip_dirs = {"dist", "node_modules", "vendor", ".git", ".github", ".tests"}
files = []

for path in root.rglob("*"):
    if not path.is_file():
        continue
    rel = path.relative_to(root)
    if any(part.startswith(".") or part in skip_dirs for part in rel.parts):
        continue
    if path.suffix.lower() not in allowed:
        continue
    files.append(path)

(root / "dist").mkdir(exist_ok=True)
zip_path = root / "dist" / "woo-complete-the-look.zip"

with ZipFile(zip_path, "w", ZIP_DEFLATED) as archive:
    for path in sorted(files):
        archive.write(
            path,
            "woo-complete-the-look/" + path.relative_to(root).as_posix(),
        )

print(f"Packaged {len(files)} plugin files: {zip_path.relative_to(root)}")
