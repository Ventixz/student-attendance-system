# Docker Guide: run the attendance website on any laptop

Docker packages the whole website (the web server, the database and everything they need) into a ready-made bundle. On each laptop you install **one program, Docker Desktop**, then **double-click `start.bat`**. There's no XAMPP, no special folder and no settings to copy.

> **Docker or XAMPP?** Both run the same website. Choose **Docker** if you'll use it on several laptops or want the simplest restart. Choose **XAMPP** ([SETUP-GUIDE.md](SETUP-GUIDE.md)) if Docker Desktop won't install on your computer (for example, an older or locked-down laptop).

> ⚠️ **Each laptop keeps its own records.** Attendance taken on Laptop A doesn't appear on Laptop B by itself. If several people need the *same* records, run it on one laptop and let the others open it over Wi-Fi (Part 6), or move the data across (Part 7).

---

## What you need

- Windows 10/11 (64-bit) or a Mac
- About 4 GB of free disk space and 8 GB of memory (RAM) recommended
- An internet connection for the first start (to download the parts)

---

## Part 1: Install Docker Desktop (once per laptop)

1. Go to **https://www.docker.com/products/docker-desktop/**
2. Click **Download Docker Desktop** and choose your system:
   - **Windows**: "Download for Windows – AMD64" (choose ARM64 only if your laptop has an ARM/Snapdragon chip).
   - **Mac**: "Apple Silicon" if **Apple menu → About This Mac** shows *Chip: Apple M1/M2/M3…*, or "Intel Chip" if it shows *Processor: Intel*.
3. Open the downloaded installer and keep the default choices. On Windows, leave **"Use WSL 2"** ticked.
4. **Restart the computer** when asked.
5. Open **Docker Desktop** from the Start menu (or Applications on a Mac).
   - Accept the agreement. Docker Desktop is free for personal use, education and small organisations.
   - If it asks you to sign in, you can click **Skip** or **Continue without signing in**.
6. Wait until the bottom-left corner of Docker Desktop shows **"Engine running"** in green.

> 💡 **Recommended:** in Docker Desktop, open **Settings (⚙) → General** and tick **"Start Docker Desktop when you sign in to your computer"**. After the first start, the website then comes back on its own whenever the laptop is turned on.

---

## Part 2: Download the website

1. Go to **https://github.com/Ventixz/student-attendance-system**
2. Click the green **`<> Code`** button → **Download ZIP**.
3. Right-click the downloaded ZIP → **Extract All…** → **Extract**.
4. Put the extracted folder anywhere you like, for example in **Documents**. Unlike XAMPP, the location doesn't matter.

Open the folder and check you can see **`start.bat`**, **`stop.bat`** and **`compose.yaml`**. (If you only see one folder, open it. The ZIP sometimes makes a folder inside a folder.)

---

## Part 3: Start the website

### Windows
1. Make sure Docker Desktop shows **Engine running**.
2. Double-click **`start.bat`**.
   - If Windows shows **"Windows protected your PC"**, click **More info → Run anyway**. The file only contains the start-up steps.
3. A black window shows progress. **The very first time takes about 3–10 minutes** while Docker downloads the parts. Later starts take a few seconds.
4. Your browser opens **http://localhost:8080/** by itself.

### Mac
1. Make sure Docker Desktop shows **Engine running**.
2. Open **Terminal** (press ⌘ + Space, type *Terminal*, press Enter).
3. Type `sh ` (the letters **s h** and a **space**), drag **`start.sh`** from the folder into the Terminal window, and press **Enter**.
4. When it finishes, the browser opens **http://localhost:8080/**.

---

## Part 4: One-time setup (once per laptop)

1. Go to **http://localhost:8080/install**
2. Create your **administrator account**: full name, username, and a password of at least 8 characters. **Write the password down.**
3. Leave **Add sample data** ticked to explore with practice data, or untick it to start empty.
4. Click **Install**, then **Go to admin login**.

From here everything works exactly as in **Parts 6 and 7 of [SETUP-GUIDE.md](SETUP-GUIDE.md)**: adding teachers, classes and students, taking attendance, and reports. Just use **`localhost:8080`** wherever that guide says `localhost/attendance`.

| Page | Address |
|---|---|
| Website / student lookup | http://localhost:8080/ |
| Staff login | http://localhost:8080/admin/login |
| Backups (phpMyAdmin) | http://localhost:8081/ |

---

## Part 5: Everyday use

- **Start:** open Docker Desktop, then double-click **`start.bat`** (Mac: `sh start.sh`). If you set Docker Desktop to start with the computer and didn't stop the website, it's already running. Just open http://localhost:8080/.
- **Stop:** double-click **`stop.bat`** (Mac: `sh stop.sh`). **Your data is kept.**
- **Close Docker Desktop** when you don't need the website to free up memory. The website stops with it, and the data is kept.

---

## Part 6: Share one set of records over Wi-Fi

Run the website on one "main" laptop and let teachers open it from their own laptops or phones on the same Wi-Fi:

1. On the main laptop, find its address. On Windows, click **Start**, type **cmd**, press Enter, type **`ipconfig`** and press Enter. Note the **IPv4 Address**, for example `192.168.1.23`. On a Mac, go to **System Settings → Wi-Fi → Details** to find the IP address.
2. On the other devices, open **http://192.168.1.23:8080/**, using your numbers.
3. If it doesn't load, allow Docker Desktop through the firewall when Windows asks, or ask your IT person to allow port **8080**.

The main laptop must be on and running the website for others to use it.

---

## Part 7: Back up, or move the records to another laptop

**Make a backup**
1. Go to **http://localhost:8081/** and sign in with username **`attendance`** and password **`attendance`**.
2. Click **attendance_db** on the left → **Export** tab → **Export**.
3. Keep the downloaded `attendance_db.sql` file safe, for example on a USB drive or in cloud storage.

**Move the records to another laptop** (or from an XAMPP setup to Docker)
1. Make a backup on the old laptop (above).
2. On the new laptop, do Parts 1–3 but **skip Part 4** (don't run the installer).
3. Go to **http://localhost:8081/**, sign in (`attendance` / `attendance`), click **attendance_db** → **Import** tab → **Choose File** → pick the backup → **Import**.
4. Sign in at http://localhost:8080/admin/login with your usual username and password. Everything is there.

---

## Changing settings (school name, time zone, dark/light)

1. In the website folder, right-click **`compose.yaml`** → **Open with** → **Notepad** (Mac: TextEdit).
2. Find these lines and change only the text **inside the quote marks**:
   ```yaml
   SCHOOL_NAME: "Your School Name"
   APP_TIMEZONE: "Asia/Shanghai"
   DEFAULT_THEME: "dark"
   ```
   Time zone examples: `"Asia/Manila"`, `"America/New_York"`, `"Europe/London"` ([full list](https://www.php.net/timezones)). Theme: `"dark"` or `"light"`.
3. Save the file, then double-click **`start.bat`** again to apply the change.

---

## Updating to a newer version

1. Download the new ZIP from GitHub (Part 2) and extract it. You can replace the old folder.
2. Double-click **`start.bat`** in the new folder.

Your records are stored inside Docker, not in the folder, so they carry over automatically.

---

## Troubleshooting

**"Docker Desktop is not running"**
Open Docker Desktop and wait for **Engine running**, then try again.

**Docker Desktop says "WSL needs updating" or won't start (Windows)**
Click **Start**, type **PowerShell**, open it, type **`wsl --update`**, press Enter, then restart Docker Desktop.

**"Virtualization is not enabled" / "Hardware assisted virtualization"**
This setting is switched off inside the laptop's BIOS. Search the web for *"enable virtualization"* with your laptop brand, or ask a technical person. If that's not possible, use the XAMPP method ([SETUP-GUIDE.md](SETUP-GUIDE.md)) instead.

**"port is already allocated" or "address already in use"**
Another program is using port 8080 or 8081. Open `compose.yaml` in Notepad and change `"8080:80"` to `"8090:80"` (or `8081` to `8091`), save, and run `start.bat` again. Then use **http://localhost:8090/**. The browser may still open 8080 by itself, so type the new address.

**The first start fails partway through**
This is usually an internet hiccup during the download. Double-click `start.bat` again. It continues where it stopped.

**You (the only administrator) forgot your password**
Follow **"You (the only administrator) forgot your password"** in [SETUP-GUIDE.md](SETUP-GUIDE.md). Use **http://localhost:8081/** (sign in as `attendance` / `attendance`) for phpMyAdmin and **http://localhost:8080/install** for the installer.

**Start completely fresh (⚠️ deletes ALL attendance records on this laptop)**
Make a backup first if you might need the records. Then, in a terminal opened in the website folder, run:
```
docker compose down -v
```
Then run `start.bat` and do Part 4 again.
