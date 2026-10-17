# Setup Guide: Student Attendance Monitoring System

This guide walks you through putting the attendance website on a Windows computer, step by step. You don't need any technical knowledge. Set aside about **20 minutes**.

When you're done, you'll open the website in your web browser (Chrome, Edge, Firefox…) at **http://localhost/attendance/**.

> **What does "localhost" mean?** The website runs on your own computer, not on the internet. Only your computer, plus devices on the same Wi-Fi if you choose (see Part 8), can open it.

> 🐳 **Setting it up on several laptops?** The **[Docker Guide](DOCKER-GUIDE.md)** is easier. Install one program, then double-click `start.bat`. No XAMPP and no folder juggling.

---

## What you need

- A Windows 10 or 11 computer
- An internet connection (only for the downloads)
- About 1 GB of free disk space

---

## Part 1: Install XAMPP (the free program that runs the website)

XAMPP is a free program that lets your computer run websites. You only install it once.

1. Open your web browser and go to **https://www.apachefriends.org**
2. Click the big **XAMPP for Windows** download button. Wait for the file to finish downloading.
3. Open the downloaded file (it's usually in your **Downloads** folder and named something like `xampp-windows-x64-8.2.12-0-VS16-installer.exe`).
4. If Windows asks **"Do you want to allow this app to make changes?"**, click **Yes**.
5. If a message about **"User Account Control (UAC)"** appears, click **OK**. It's only a warning.
6. Click **Next** on each screen, keeping all the default choices.
   - ⚠️ When it asks for the installation folder, keep it as **`C:\xampp`**. Don't change it.
7. Click **Finish** when it's done. If it offers to open the Control Panel, you can let it.
8. If Windows Firewall asks about **Apache HTTP Server** or **mysqld**, tick **Private networks** and click **Allow access**.

✅ XAMPP is now installed.

---

## Part 2: Download the attendance website

1. Go to this project's page on GitHub: **https://github.com/Ventixz/student-attendance-system**
   - *If the page says "404", the project is private. Ask the owner to invite your GitHub account, sign in, and try again.*
2. Click the green **`<> Code`** button.
3. Click **Download ZIP**. A file named `student-attendance-system-main.zip` downloads.

---

## Part 3: Put the website in the right folder

This part matters most. The website must end up in exactly the right place.

1. Open your **Downloads** folder and find `student-attendance-system-main.zip`.
2. **Right-click** it and choose **Extract All…**, then click **Extract**.
3. A new folder opens. Keep opening folders until you see files named **`index.php`**, **`install.php`**, **`config.php`** and folders named **`admin`**, **`assets`** and so on.
   - The ZIP often creates a folder *inside* a folder with the same name. Go into the inner one.
4. Go **one level up** so you're looking at the folder that *contains* those files. It's called `student-attendance-system-main`.
5. **Rename** that folder to exactly: **`attendance`** (all lowercase). To rename, right-click it and choose **Rename**.
6. **Cut** the `attendance` folder (right-click → **Cut**).
7. Open **File Explorer**, click in the address bar at the top, type **`C:\xampp\htdocs`** and press **Enter**.
8. Right-click an empty space in that window and choose **Paste**.

**Check:** open `C:\xampp\htdocs\attendance`. You should see `index.php`, `install.php`, `config.php` and the `admin` folder **directly inside**, not inside another folder.

✅ The website files are in place.

---

## Part 4: Start XAMPP

**Do this every time you turn the computer on and want to use the website.**

1. Click the **Start** menu, type **XAMPP**, and open **XAMPP Control Panel**.
2. Click the **Start** button next to **Apache**.
3. Click the **Start** button next to **MySQL**.
4. Wait a few seconds. Both names, **Apache** and **MySQL**, should turn **green**.

If one of them doesn't turn green, see **Troubleshooting** at the end of this guide.

> 💡 You can minimise the XAMPP window, but **don't close it** while you're using the website.

---

## Part 5: One-time setup

1. Open your web browser and go to: **http://localhost/attendance/install**
2. You'll see a checklist. The first line, **MySQL server**, should have a green tick.
3. Fill in the form to create **your administrator account**. This is the main account that can manage everything:
   - **Full name**: your name
   - **Username**: what you'll type to sign in (for example `admin` or your first name)
   - **Password**: at least 8 characters. **Write it down somewhere safe.**
   - **Confirm password**: type the same password again
4. **Add sample data**:
   - Leave it **ticked** if you'd like to explore with practice data first (3 classes, 15 pretend students and three weeks of attendance). You can delete it later.
   - **Untick** it if you want to start with an empty system.
5. Click **Install**.
6. When you see **"Installation complete"**, click **Go to admin login**.

> After setup, the installer page hides itself. Visiting `/attendance/install` again just takes you to the home page.

✅ The website is ready.

---

## Part 6: Sign in and set things up

Sign in at **http://localhost/attendance/admin/login** with the username and password you just created.

Set things up in this order, using the menu on the left:

1. **Staff Accounts** → add an account for each **teacher**. Choose a username and password for them and give it to them privately.
2. **Classes & Subjects** → add each class (for example *MATH101, College Algebra*) and choose its **Teacher / Instructor**.
3. **Students** → add each student with their **Student ID**, first name, last name and section, and tick the classes they're in.
   - Another way: open **Classes & Subjects**, click **Students** next to a class, and tick everyone in that class at once.

**Removing the sample data** (if you added it): in **Classes & Subjects**, delete CS101, CS102 and GE103. In **Students**, delete the students with IDs `2026-0001` to `2026-0015`.

---

## Part 7: Everyday use

**Taking attendance (teachers and admins)**
1. Click **Take Attendance**.
2. Choose the **class** and the **date** (today is already filled in), then click **Load class list**.
3. For each student, click **Present**, **Late** or **Absent**. Tip: **All Present** marks everyone in one click, and then you just change the few who are late or absent.
4. Click **Save attendance**. The date and time are saved automatically.

Made a mistake? Open the same class and date again, change the marks, and save. The records are updated, not duplicated.

**Quick Entry**: mark one student at a time by typing their Student ID. Their name fills in automatically.

**Reports**: the **Reports** section of the menu has:
- **Daily Attendance**: everyone in each class for a chosen day
- **Present / Absent / Late Students**: lists for any date range
- **Student Report**: one student's full history and percentage
- **Summary & Percentage**: attendance percentage for every student and class

Every report has a **Print** button and an **Export CSV** button. CSV files open in Excel.

**Students checking their own attendance**: on the home page (http://localhost/attendance/), students type their Student ID and last name to see their own attendance.

**Dark mode / light mode**: the website opens in dark mode. To switch, click the **sun** ☀️ or **moon** 🌙 button at the top of any page. Your browser remembers your choice.

---

## Part 8 (optional): Use it from other computers or phones on the same Wi-Fi

Teachers can open the website from their own laptops or phones. The main computer must be on, connected to the same Wi-Fi or network, and running XAMPP.

1. On the main computer, click **Start**, type **cmd** and press **Enter**. A black window opens.
2. Type **`ipconfig`** and press **Enter**.
3. Find the line **IPv4 Address**. It looks like `192.168.1.23`.
4. On the other device, open the browser and go to **http://192.168.1.23/attendance/**, using *your* numbers.

If it doesn't load, Windows Firewall may be blocking it. Open the XAMPP Control Panel, click **Stop** then **Start** on Apache, and click **Allow access** if Windows asks.

---

## Part 9: Back up your data (recommended weekly)

All attendance is stored in a database on the main computer. To make a backup copy:

1. With XAMPP running, go to **http://localhost/phpmyadmin**
2. In the list on the left, click **attendance_db**.
3. Click the **Export** tab at the top, then click **Export** (or **Go**).
4. A file named `attendance_db.sql` downloads. Keep it somewhere safe, such as a USB drive or cloud storage.

**To restore a backup:** go to phpMyAdmin → click **attendance_db** → **Import** tab → **Choose File** → pick your `.sql` file → **Import**.

---

## Changing settings (school name, time zone, dark/light default)

1. Open `C:\xampp\htdocs\attendance`.
2. Right-click **`config.php`** → **Open with** → **Notepad**.
3. Change only the **last text in quote marks** on the line, which is the current value. Keep the quote marks `'`, the brackets and the semicolon `;` exactly as they are.

| What to change | Find this line | Example |
|---|---|---|
| School name | `define('SCHOOL_NAME', setting('SCHOOL_NAME', 'Your School Name'));` | `define('SCHOOL_NAME', setting('SCHOOL_NAME', 'Riverside High School'));` |
| Time zone (if times look wrong) | `define('APP_TIMEZONE', setting('APP_TIMEZONE', 'Asia/Shanghai'));` | `'Asia/Manila'`, `'America/New_York'`, `'Europe/London'` ([full list](https://www.php.net/timezones)) |
| Default look | `define('DEFAULT_THEME', setting('DEFAULT_THEME', 'dark'));` | `'light'` |
| "Low attendance" warning level | `define('LOW_ATTENDANCE_THRESHOLD', (int) setting('LOW_ATTENDANCE_THRESHOLD', '75'));` | `'80'` |

4. Click **File → Save**, then refresh the website in your browser.

---

## Troubleshooting

**Apache won't turn green**
- Another program is probably using the same connection "port". Common culprits are **Skype** and **Microsoft IIS**. Close Skype completely and try **Start** again.
- If it still won't start, change Apache's port:
  1. In XAMPP Control Panel, click **Config** next to Apache → **Apache (httpd.conf)**. Notepad opens.
  2. Press **Ctrl + F**, search for `Listen 80`, and change it to `Listen 8080`.
  3. Search for `ServerName localhost:80` and change it to `ServerName localhost:8080`.
  4. Save, close Notepad, and click **Start** next to Apache again.
  5. From now on, use **http://localhost:8080/attendance/**. Add `:8080` after `localhost` in every address in this guide.

**MySQL won't turn green**
- Close any other database programs and restart the computer, then try again.
- If you've installed MySQL separately in the past, it may be using the same port. Ask a technical friend for help if it still won't start.

**The page says "Not Found"**
- Check Part 3 again. The folder must be exactly `C:\xampp\htdocs\attendance`, with `index.php` directly inside it.
- Check the address is spelled **`attendance`**, all lowercase.

**The page says "Cannot reach MySQL" or "Database not set up yet"**
- Open XAMPP Control Panel and make sure **MySQL** is green. If it says "Database not set up yet", click **Run the installer** and complete Part 5.

**The browser says "This site can't be reached"**
- XAMPP isn't running. Do Part 4 again.

**A teacher forgot their password**
- Sign in as the administrator → **Staff Accounts** → click the ✏️ edit button next to the teacher → type a new password → **Save changes**.

**"Too many failed attempts"**
- The account locks for 5 minutes after 5 wrong passwords. Wait 5 minutes and try again.

**You (the only administrator) forgot your password**
1. Go to **http://localhost/phpmyadmin** → click **attendance_db** on the left → click the **users** table.
2. Click **Edit** on your account's row. Change **role** from `admin` to `teacher` and click **Go**.
3. Go to **http://localhost/attendance/install**. Because no administrator exists now, it lets you create a new one. **Untick "Add sample data"** and click Install.
4. Sign in with the new account → **Staff Accounts** → edit your old account → set a new password, change **Role** back to **Administrator**, and save. Your attendance records are all kept.

---

Need more detail? See **README.md** for the technical overview.
