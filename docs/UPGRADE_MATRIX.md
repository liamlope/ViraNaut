# ViraNaut Upgrade Matrix — Mirza 0.2.2 + Pro 6.7

| Feature | Mirza 0.2.2 | ViraNaut pre-upgrade | Action | Target files |
|---------|-------------|----------------------|--------|--------------|
| lang/ per-user i18n | yes | text.json only | PORT + unified loader | function.php, lang/ |
| mirza_agent panel | yes | no | PORT | mirza_agent.php, panels.php, admin.php, keyboard.php |
| Pasarguard panel | yes | no | PORT | admin.php, keyboard.php, panels.php |
| SMS card receipt prompt | no | yes | KEEP | card_receipt_prompt.php |
| croncard auto-confirm | yes | alias only | MERGE dual-mode | croncard.php, PaySetting |
| Extended x-ui_single | stub | yes (1591 lines) | KEEP Vira | x-ui_single.php |
| Web panel (31 pages) | 12 pages | yes | KEEP | panel/ |
| ViraNaut_manage.sh | no | yes | KEEP + extend | ViraNaut_manage.sh |
| Pay after max limit | Pro 6.7 | block only | IMPLEMENT | function.php, index.php |
| Unknown command reply | Pro 6.7 | no | IMPLEMENT | index.php, setting |
| Agent web panel | Pro 6.7 | no | BUILD | agent-panel/ |
| Ilan panel | Pro 6.7 | no | BUILD | ilan.php |
| Site admin | Pro 6.7 | no | BUILD | site-admin/ |

Legend: **KEEP** = preserve ViraNaut · **PORT** = merge from Mirza · **BUILD** = new from changelog
