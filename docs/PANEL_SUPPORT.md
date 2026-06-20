# Panel Support Matrix — ViraNaut 3.1

| Type | Driver | Status | create | DataUser | revoke | remove | modify | extend | vol/time | Notes |
|------|--------|--------|--------|----------|--------|--------|--------|--------|----------|-------|
| marzban | Marzban.php | full | yes | yes | yes | yes | yes | yes | yes | API v0/v1 |
| pasarguard | Marzban.php | full | yes | yes | yes | yes | yes | yes | yes | Stored as marzban + version_panel=1 |
| marzneshin | marzneshin.php | full | yes | yes | yes | yes | yes | yes | yes | |
| x-ui_single | x-ui_single.php | full+ | yes | yes | yes | yes | yes | yes | yes | Vira extended driver |
| alireza_single | alireza_single.php | full | yes | yes | yes | yes | yes | yes | yes | Uses x-ui login |
| hiddify | hiddify.php | partial | yes | yes | yes* | yes | yes | yes | yes | *revoke via UUID regen |
| WGDashboard | WGDashboard.php | full | yes | yes | no | yes | yes | yes | yes | WireGuard |
| s_ui | s_ui.php | full | yes | yes | yes | yes | yes | yes | yes | |
| ibsng | ibsng.php | partial | yes | yes | no | yes | no | no | no | |
| mikrotik | mikrotik.php | partial | yes | yes | no | yes | no | no | no | |
| mirza_agent | mirza_agent.php | partial | yes | yes | yes | yes | no | yes | yes | reset via reset_usage API |
| ilan | ilan.php | full* | yes | yes | yes | yes | no | yes | yes | *Generic REST — mock tests |
| Manualsale | — | full | yes | yes | no | yes | no | no | no | Internal pool |

Legend: **full** = all bot flows · **partial** = limited ops · **full*** = REST generic without live panel doc

Testing without live panels: `tests/Unit/IlanDriverTest.php`, `tests/PanelMock/*`.
