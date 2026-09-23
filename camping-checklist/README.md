# Camping Checklist (QNAP)

Files: `index.html`, `api.php`, `data/` (the shared checklist is saved to `data/state.json`, created on first load).

## Deploy to QNAP (QTS 5.2)

1. **Turn on Web Server:** Control Panel → Applications → Web Server → check *Enable Web Server* (port 80) → Apply.
2. **Copy files:** File Station → `Web` share → create folder `camping` → upload `index.html`, `api.php` and the `data` folder.
3. **Set permissions (SSH):** Control Panel → Network & File Services → Telnet/SSH → enable SSH. Then from a computer:
   ```bash
   ssh admin@NAS-IP
   cd /share/Web/camping
   mkdir -p data
   chown -R httpdusr:administrators data
   chmod 775 data
   chmod 644 index.html api.php
   ```
   No SSH? File Station → right-click `data` → Properties → Permissions → give **Everyone** Read/Write.
4. **Open on iPhone:** Safari → `http://NAS-IP/camping/` → Share → *Add to Home Screen*. Do the same on your wife's phone.
5. **Check it:** tick an item on one phone; the other updates within ~4 seconds.

## Notes
- LAN only; no login. Don't port-forward it to the internet.
- Reset list for a new trip: "Reset all", or delete `data/state.json` to restore the original items.
- Error banner "Cannot write data/state.json" = step 3 permissions.
