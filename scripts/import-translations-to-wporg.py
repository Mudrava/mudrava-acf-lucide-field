#!/usr/bin/env python3
"""
Helper script to import translations to WordPress.org GlotPress
Requires: requests library
Usage: python import-translations-to-wporg.py
"""

import requests
import os
import glob
from pathlib import Path

# WordPress.org GlotPress API endpoints
BASE_URL = "https://translate.wordpress.org"
PROJECT_SLUG = "wp-plugins/mudrava-acf-lucide-field/stable"

# Language code mapping (WordPress.org uses different codes)
LANG_MAP = {
    "uk": "uk",
    "de_DE": "de",
    "fr_FR": "fr",
    "es_ES": "es",
    "it_IT": "it",
    "pt_BR": "pt-br",
    "nl_NL": "nl",
    "ja": "ja",
    "zh_CN": "zh-cn",
    "pl_PL": "pl",
    "tr_TR": "tr",
}

def print_instructions():
    """Print manual import instructions"""
    print("=" * 80)
    print("MANUAL IMPORT INSTRUCTIONS FOR WORDPRESS.ORG")
    print("=" * 80)
    print()
    print("As the plugin author, you can manually import translations:")
    print()
    print("1. Go to: https://translate.wordpress.org/projects/wp-plugins/mudrava-acf-lucide-field/")
    print()
    print("2. Wait for POT file to be imported (may take a few hours)")
    print("   - WordPress.org scans SVN trunk/languages/*.pot automatically")
    print()
    print("3. For each language, click on it and look for 'Import Translations' button")
    print()
    print("4. If you don't see the button, you may need to request PTE rights:")
    print("   - Go to: https://make.wordpress.org/polyglots/")
    print("   - Request 'Project Translation Editor' rights for your plugin")
    print()
    print("=" * 80)
    print("AVAILABLE TRANSLATION FILES:")
    print("=" * 80)
    
    lang_dir = Path(__file__).parent.parent / "languages"
    for po_file in sorted(lang_dir.glob("*.po")):
        lang_code = po_file.stem.replace("mudrava-acf-lucide-field-", "")
        wporg_code = LANG_MAP.get(lang_code, lang_code)
        url = f"{BASE_URL}/projects/{PROJECT_SLUG}/{wporg_code}/default/import-translations"
        print(f"\n{lang_code:12} → {wporg_code:10} ({po_file.name})")
        print(f"{'':12}   Import URL: {url}")
    
    print()
    print("=" * 80)
    print("QUICK TIP:")
    print("=" * 80)
    print("The yellow banner will disappear once WordPress.org recognizes translations.")
    print("This usually happens within 24 hours after POT import + translation approval.")
    print()

if __name__ == "__main__":
    print_instructions()
