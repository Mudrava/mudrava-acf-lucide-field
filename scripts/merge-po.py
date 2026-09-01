#!/usr/bin/env python3
"""Merge catalog .po files against the current .pot and regenerate .mo files.

Usage: python3 scripts/merge-po.py

Without a full msgmerge toolchain this script keeps existing translations for
still-present msgids, adds untranslated (empty) entries for new msgids and
drops obsolete ones. Compiled .mo files are rewritten from the merged
catalogs, so shipped translations never reference removed strings.
"""

import datetime
import glob
import os
import re
import struct
import sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
LANG = os.path.join(ROOT, "languages")


def unescape(value):
    out = []
    i = 0
    while i < len(value):
        ch = value[i]
        if ch == "\\" and i + 1 < len(value):
            nxt = value[i + 1]
            if nxt == "u" and value[i + 2 : i + 6] == "00a0":
                out.append("\u00a0")
                i += 6
                continue
            mapping = {"n": "\n", "t": "\t", "r": "\r", '"': '"', "\\": "\\"}
            out.append(mapping.get(nxt, nxt))
            i += 2
        else:
            out.append(ch)
            i += 1
    return "".join(out)


def escape(value):
    return (
        value.replace("\\", "\\\\")
        .replace('"', '\\"')
        .replace("\n", "\\n")
        .replace("\t", "\\t")
        .replace("\r", "\\r")
    )


def parse_po(path):
    entries = []
    current = None
    field = None

    def flush():
        if current and (current["msgid"] or current["msgstr"]):
            entries.append(current)

    with open(path, encoding="utf-8") as handle:
        for raw in handle:
            line = raw.strip()

            if line.startswith("msgid"):
                flush()
                current = {"msgid": "", "msgstr": "", "obsolete": False}
                field = "msgid"
                rest = line[5:].strip()
                if rest:
                    current["msgid"] = unescape(rest.strip('"'))
                continue

            if line.startswith("msgstr"):
                field = "msgstr"
                rest = line[6:].strip()
                if current is not None and rest:
                    current["msgstr"] = unescape(rest.strip('"'))
                continue

            if line.startswith("#~"):
                if current is not None:
                    current["obsolete"] = True
                elif entries:
                    entries[-1]["obsolete"] = True
                continue

            if line.startswith("#"):
                continue

            if line.startswith('"') and line.endswith('"') and current is not None:
                current[field] += unescape(line[1:-1])
                continue

    flush()
    return entries


def po_text(header_msgstr, merged):
    lines = ['msgid ""', 'msgstr ""']

    for line in header_msgstr.rstrip("\n").split("\n"):
        lines.append('"%s\\n"' % escape(line))

    lines.append("")

    for entry in merged:
        if not entry["msgid"]:
            continue
        lines.append('msgid "%s"' % escape(entry["msgid"]))
        lines.append('msgstr "%s"' % escape(entry["msgstr"]))
        lines.append("")

    return "\n".join(lines) + "\n"


def next_prime(n):
    def is_prime(x):
        if x < 2:
            return False
        i = 2
        while i * i <= x:
            if x % i == 0:
                return False
            i += 1
        return True

    while not is_prime(n):
        n += 1
    return n


def build_mo(catalog):
    """Compile a list of {msgid,msgstr} entries (header entry first) to .mo bytes."""
    entries = [e for e in catalog if e["msgid"] is not None and e["msgstr"] is not None]

    ids_blob = b""
    trans_blob = b""
    ids_index = []
    trans_index = []

    for entry in entries:
        key = entry["msgid"].encode("utf-8")
        val = entry["msgstr"].encode("utf-8")
        ids_index.append((len(key), len(ids_blob)))
        trans_index.append((len(val), len(trans_blob)))
        ids_blob += key + b"\x00"
        trans_blob += val + b"\x00"

    count = len(entries)
    hash_size = next_prime(max(count * 4, 7))
    hash_table = [0xFFFFFFFF] * hash_size

    ids_base = 28 + 8 * count + 8 * count + 4 * hash_size
    trans_base = ids_base + len(ids_blob)

    for idx, entry in enumerate(entries):
        if not entry["msgid"]:
            continue
        key = entry["msgid"].encode("utf-8")
        h = 0
        for byte in key:
            h = (h * 33 + byte) & 0x7FFFFFFF
        i = h % hash_size
        while hash_table[i] != 0xFFFFFFFF:
            i = (i + 1) % hash_size
        hash_table[i] = idx

    out = struct.pack(
        "<7I",
        0x950412DE,
        0,
        count,
        28,
        28 + 8 * count,
        hash_size,
        28 + 16 * count,
    )
    out += b"".join(struct.pack("<II", n, ids_base + off) for n, off in ids_index)
    out += b"".join(struct.pack("<II", n, trans_base + off) for n, off in trans_index)
    out += b"".join(struct.pack("<I", slot) for slot in hash_table)
    out += ids_blob
    out += trans_blob

    return out


def extract_from(header, label):
    stop = "(?=\\n|Project-Id|Report-Msgid|POT-Creation|PO-Revision|Last-Translator|Language-Team|Language:|MIME-Version|Content-Type|Content-Transfer|Plural-Forms|X-Generator|$)"

    match = re.search(re.escape(label) + r": (.*?)" + stop, header, re.DOTALL)

    return match.group(1).strip() if match else ""


def main():
    pot_path = os.path.join(LANG, "mudrava-acf-lucide-field.pot")

    if not os.path.isfile(pot_path):
        print("missing pot: %s" % pot_path)
        return 1

    pot_entries = parse_po(pot_path)
    pot_ids = [e["msgid"] for e in pot_entries if e["msgid"]]

    changed = 0

    for po_path in sorted(glob.glob(os.path.join(LANG, "mudrava-acf-lucide-field-*.po"))):
        existing_entries = parse_po(po_path)

        lang = os.path.basename(po_path).split("-")[-1][:-3]
        pot_header = ""
        for entry in pot_entries:
            if entry["msgid"] == "":
                pot_header = entry["msgstr"]
                break
        existing_header = ""
        for entry in existing_entries:
            if entry["msgid"] == "":
                existing_header = entry["msgstr"]
                break

        stop = "(?=\n|Project-Id|Report-Msgid|POT-Creation|PO-Revision|Last-Translator|Language-Team|Language:|MIME-Version|Content-Type|Content-Transfer|Plural-Forms|X-Generator|$)"

        def extract(label):
            match = re.search(re.escape(label) + r": (.*?)" + stop, existing_header, re.DOTALL)

            return match.group(1).strip() if match else ""

        revision = extract("PO-Revision-Date")
        plural = extract("Plural-Forms")
        last_translator = extract("Last-Translator")
        language_team = extract("Language-Team")

        pot_date = extract_from(pot_header, "POT-Creation-Date")

        if not pot_date:
            pot_date = datetime.datetime.now(datetime.timezone.utc).strftime("%Y-%m-%d %H:%M+0000")

        header_msgstr = (
            "Project-Id-Version: Mudrava Icon Field for ACF with Lucide 1.2.0\n"
            "Report-Msgid-Bugs-To: https://wordpress.org/support/plugin/mudrava-acf-lucide-field/\n"
        )
        header_msgstr += "POT-Creation-Date: %s\n" % pot_date
        header_msgstr += "PO-Revision-Date: %s\n" % (revision if revision else "YEAR-MO-DA HO:MI+ZONE")
        header_msgstr += "Last-Translator: %s\n" % (last_translator if last_translator else "FULL NAME <EMAIL@ADDRESS>")
        header_msgstr += "Language-Team: %s\n" % (language_team if language_team else "LANGUAGE <LL@li.org>")
        header_msgstr += "Language: %s\n" % lang
        header_msgstr += "MIME-Version: 1.0\n"
        header_msgstr += "Content-Type: text/plain; charset=UTF-8\n"
        header_msgstr += "Content-Transfer-Encoding: 8bit\n"
        header_msgstr += "Plural-Forms: %s\n" % (plural if plural else "nplurals=INTEGER; plural=EXPRESSION;")
        header_msgstr += "X-Generator: scripts/merge-po.py\n"

        existing = {e["msgid"]: e["msgstr"] for e in existing_entries if e["msgid"] and not e["obsolete"]}
        merged = [{"msgid": msgid, "msgstr": existing.get(msgid, "")} for msgid in pot_ids]

        catalog = [{"msgid": "", "msgstr": header_msgstr}] + merged

        with open(po_path, "w", encoding="utf-8") as handle:
            handle.write(po_text(header_msgstr, merged))

        mo_path = po_path[:-3] + ".mo"

        with open(mo_path, "wb") as handle:
            handle.write(build_mo(catalog))

        changed += 1

    print("merged and recompiled %d catalogs" % changed)
    return 0


if __name__ == "__main__":
    sys.exit(main())
