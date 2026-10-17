# Aptitude Test Management System

A web-based objective testing platform built with PHP and MySQL. Students attempt
timed, topic-wise quizzes and get instant scored results; administrators publish
quizzes, manage students and monitor performance from a single dashboard.

Originally derived from an "Online Quiz System" project, the application has been
restructured into a conventional PHP layout and given a modern, responsive,
dependency-free user interface.

---

## Features

### For students

- Register and sign in with a name, e-mail, password and college.
- Browse every published quiz with its question count and maximum marks.
- Attempt quizzes one question at a time with a progress indicator.
- Receive an instant result showing score, correct answers, wrong answers and
  overall cumulative score.
- Restart any quiz; the previous attempt is replaced and the ranking recalculated.
- Review a full attempt history and a global leaderboard.

### For administrators

- Separate administrator sign-in, isolated from student sessions.
- Overview tiles for quizzes, questions, students and total attempts.
- Create a quiz by defining its title, question count and marking scheme
  (marks per correct answer and penalty per wrong answer).
- Add questions with four options each and mark the correct answer.
- Delete a quiz together with its questions, options, answers and attempt history.
- Delete a student together with their attempts and ranking entry.
- View the ranking table and the full student list.

### Engineering

- Centralised configuration with environment-variable overrides.
- CSRF tokens on every state-changing request.
- Session fixation protection via `session_regenerate_id()`.
- Server-side validation with flash-message feedback.
- Escape-on-output helper (`e()`) to prevent cross-site scripting.
- Responsive, accessible UI with no build step and no third-party CSS framework.

---

## Project structure

```
Aptitude_Test_Management_System/
├── index.php                  Public landing page
├── login.php                  Student sign-in
├── register.php               Student registration
├── logout.php                 Ends the session
│
├── actions/                   State-changing request handlers (POST only)
│   ├── admin.php              Create/delete quizzes, delete students
│   └── quiz.php               Records an answer and advances the attempt
│
├── admin/
│   ├── login.php              Administrator sign-in
│   └── dashboard.php          Overview, students, ranking, quiz builder
│
├── student/
│   └── dashboard.php          Quiz list, quiz runner, result, history, ranking
│
├── includes/                  Shared view and bootstrapping code
│   ├── bootstrap.php          Session, helpers, auth guards, CSRF
│   ├── header.php             Page head, navigation, flash messages
│   ├── footer.php             Page footer
│   └── auth-layout.php        Split layout for the sign-in screens
│
├── config/
│   ├── config.php             Application constants and base-URL detection
│   └── database.php           mysqli connection and query helpers
│
├── assets/
│   ├── css/app.css            Design system and all component styles
│   ├── js/app.js              Progressive enhancements
│   └── img/logo.png           Favicon and brand mark
│
├── database/
│   └── sourcecodester_exam.sql   Schema plus sample data
│
├── docs/
│   └── quiz-report.docx       Original project report
│
└── README.md
```

### Request flow

1. A page such as `student/dashboard.php` includes `includes/bootstrap.php`,
   which starts the session, loads the database layer and exposes helpers.
2. The page enforces access with `require_student()` or `require_admin()`.
3. Any form submits to a handler under `actions/`. The handler calls
   `verify_csrf()`, writes to the database and redirects.
4. Redirects are always followed by a fresh `GET`, so refreshing never
   re-submits a form (post/redirect/get).

---

## Requirements

- PHP 8.0 or newer with the `mysqli` extension
- MySQL 5.7+ or MariaDB 10.2+
- Any web server (Apache, nginx) — or PHP's built-in server for development

---

## Installation

### 1. Get the code

Place the project in your web root, for example
`/var/www/html/Aptitude_Test_Management_System`.

### 2. Create the database

```bash
mysql -u root -p -e "CREATE DATABASE sourcecodester_exam CHARACTER SET utf8mb4;"
mysql -u root -p sourcecodester_exam < database/sourcecodester_exam.sql
```

The dump creates the `admin`, `answer`, `history`, `options`, `questions`,
`quiz`, `rank` and `user` tables and loads sample quizzes.

### 3. Configure the connection

Defaults live in `config/config.php` and match a stock local MySQL server
(`localhost`, user `root`, empty password, database `sourcecodester_exam`).

To point the application elsewhere, either edit those constants or set
environment variables — no code change is required:

| Variable       | Default               | Purpose                      |
|----------------|-----------------------|------------------------------|
| `DB_HOST`      | `localhost`           | Database host                |
| `DB_PORT`      | `3306`                | Database port                |
| `DB_NAME`      | `sourcecodester_exam` | Database name                |
| `DB_USER`      | `root`                | Database user                |
| `DB_PASS`      | *(empty)*             | Database password            |
| `APP_BASE_URL` | auto-detected         | Base URL, for reverse proxies |

### 4. Run the application

With Apache or nginx, simply browse to the project directory.

For a quick local run:

```bash
php -S localhost:8000
```

Then open <http://localhost:8000/index.php>.

---

## Default credentials

The sample data ships with one administrator account:

| Role          | E-mail            | Password |
|---------------|-------------------|----------|
| Administrator | `admin@admin.com` | `admin`  |

Sample student accounts (`pinky@gmail.com` / `pinky`, `priyanka@gmail.com` /
`pinka`, `janobe@gmail.com` / `jan`) are included for demonstration only.

> Change the administrator password before deploying anywhere public. Passwords
> are currently stored in plain text, matching the original schema — see
> *Known limitations* below.

---

## Usage

### As a student

1. Open `index.php` and choose **Create an account**, or sign in.
2. The dashboard lists every quiz with its question count and maximum marks.
3. Choose **Start** (or **Restart** for a completed quiz) to begin.
4. Answer each question and submit to advance. The progress bar tracks position.
5. After the last question the result page shows the score breakdown.
6. Use **History** and **Ranking** in the navigation for past attempts and the
   leaderboard.

### As an administrator

1. Open `admin/login.php` and sign in with the administrator account.
2. **Overview** summarises quizzes, questions, students and attempts.
3. **Add quiz** asks for the title, question count and marking scheme, then opens
   the question builder. Each question needs four options and a correct answer.
4. **Remove quiz** lists every quiz with a confirmation prompt before deletion.
5. **Students** lists registered users; deleting one also removes their attempts
   and ranking entry.
6. **Ranking** shows students ordered by cumulative score.

---

## Database schema

| Table       | Purpose                                                       |
|-------------|---------------------------------------------------------------|
| `admin`     | Administrator accounts                                        |
| `user`      | Student accounts (name, college, e-mail, password)            |
| `quiz`      | A quiz: title, marks per correct/wrong answer, question total |
| `questions` | Questions belonging to a quiz, ordered by `sn`                |
| `options`   | The four answer choices for each question                     |
| `answer`    | The correct option for each question                          |
| `history`   | One row per student attempt: score, level, right and wrong    |
| `rank`      | Cumulative score per student, used for the leaderboard        |

---

## Security notes

Implemented in this version:

- Every write action is a `POST` guarded by a per-session CSRF token.
- All user input is escaped before it reaches SQL.
- All output is escaped with `htmlspecialchars()` before rendering.
- Sessions are regenerated on sign-in to prevent fixation.
- Student and administrator areas are protected by independent guards.
- Access control is enforced server-side, not only by hiding links.

Known limitations (unchanged from the original schema):

- Passwords are stored in plain text. Migrating to `password_hash()` /
  `password_verify()` is the recommended next step.
- The schema defines no foreign keys, so referential integrity is maintained by
  application code.
- There is no rate limiting on the sign-in forms.

---

## License

See [LICENSE](LICENSE).
