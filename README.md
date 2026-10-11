# UniHive – Unigator task

This folder answers the Unigator "UniHive understanding task" and includes a working WordPress prototype of the plan.

| Path | What it is |
|---|---|
| `docs/UniHive-Proposal.pdf` | **The submission.** It answers every question in the brief: who can view and post, the 4 categories, how UniHive differs from YMGrad, the WordPress approach step by step, plugins, and risks. |
| `docs/UniHive-Proposal.html` | Source of the PDF. Edit it, then run `npm run pdf`. |
| `docs/loom-talk-track.md` | A 4-minute script, in case you record a Loom instead of (or as well as) sending the PDF. |
| `docs/img/` | Screenshots of the prototype, taken by an automated test run. |
| `unihive/` | The WordPress plugin (the prototype). |
| `unihive.zip` | The same plugin, ready to upload to any WordPress site. |
| `blueprint.json` | Sets up the local demo: activates the plugin and creates demo members, pages and posts. |
| `tools/` | `screenshots.mjs` (end-to-end check of every role + screenshots) and `build-pdf.mjs`. |

---

## Run the demo (one command)

**Needs:** [Node.js 20+](https://nodejs.org) and an internet connection the first time (to download WordPress). You don't need PHP, MySQL or XAMPP. It uses [WordPress Playground](https://wordpress.github.io/wordpress-playground/developers/local-development/wp-playground-cli/), which runs WordPress inside Node.

```bash
npm start
```

Wait for `Ready! WordPress is running on http://127.0.0.1:9400`. Your browser opens by itself; if it doesn't, go to **http://127.0.0.1:9400/unihive/**.
Stop the demo with `Ctrl + C`. The site is saved between runs. To start over with fresh demo data:

```bash
npm run reset
```

### Switching between users

A dark **"Demo · view as"** bar at the bottom of every page logs you in as each demo user with one click:

| View as | Account | What you'll see |
|---|---|---|
| Visitor | (logged out) | Teaser only: 4 categories, live counts, "See membership plans" / "Log in" |
| Free member | `sam_free` | Teaser with "You are on the Free plan… Upgrade" |
| Pro member | `priya_pro` | Full board, can post; "My posts" has an expired post to **Renew** |
| New Pro member | `rahul_pro` | Their first post goes to **Awaiting approval** |
| Premium member | `arjun_premium` | Full board, can post and report |
| Moderator | `meera_mod` | wp-admin showing only the UniHive menu: Pending, Reported, Approve |
| Admin | `admin` | Everything, plus **UniHive → Settings** |

If you'd rather log in normally at `/wp-login.php`, every demo account uses the password `unihive-demo`, and `admin` uses `password`. These are local demo credentials only.

### A 3-minute walkthrough

1. **Visitor**: open `/unihive/` and you get the teaser. Then open any post URL, such as `/hive/uk-graduate-route-my-exact-timeline-and-documents/`, and you're redirected back with "only visible to Pro and Premium members".
2. **Pro member**: click the category tabs (Accommodation, Work Visa, Jobs, Part-time Jobs), search "Toronto", and open a post to see the contact box and **Report**.
3. **New Pro member**: open **Create a post** and submit it empty (validation), then fill it in and publish. It goes to **Awaiting approval**.
4. **Premium member**: open a post and click **Report this post**. Three reports from different members hide a post automatically.
5. **Moderator**: in **UniHive** (red count = pending), open the **Reported** view and click **Approve** on the pending post. Rahul is now trusted, so his next post goes live straight away.
6. **Admin**: **UniHive → Settings** has the approval mode, expiry days, posts/day, report threshold and the PMPro levels.

---

## Install on a real WordPress site

1. **Plugins → Add New → Upload Plugin**, choose `unihive.zip`, then click **Activate**.
2. Create a page called "UniHive" containing the shortcode `[unihive]` and add it to the menu.
3. **Membership:** install **Paid Memberships Pro** (free) and create the levels `Pro` and `Premium`. UniHive detects PMPro and uses those levels (you can change the names in **UniHive → Settings**).
   Without PMPro, access comes from the roles **Unigator Pro**, **Unigator Premium** and **UniHive Moderator**, which the plugin adds.
4. Optional: **UniHive → Settings → Create demo content**.

Keep the **Demo user switcher** setting off on a live site. It only works when `WP_ENVIRONMENT_TYPE` is `local` or `development`.

---

## How the plugin works (file map)

| File | Responsibility |
|---|---|
| `unihive.php` | Bootstrap, activation (roles, the 4 terms, daily cron) |
| `includes/post-type.php` | CPT `unihive_post` (hidden from search, REST, feeds, sitemaps), taxonomy `unihive_category`, meta, roles/capabilities |
| `includes/access.php` | `unihive_user_can_access()`: PMPro levels or the `unihive_access` capability; redirects non-members; `noindex`; no page caching |
| `includes/board.php` | `[unihive]` shortcode: teaser for non-members; tabs, search, pagination, cards and the create form for members |
| `includes/submission.php` | Create / report / delete / renew: nonces, validation, honeypot, 3 posts/day, 2 links max, first-post approval, report threshold |
| `includes/single.php` | Single post: meta line, contact box, safety tip, Report/Delete; shows "Priya S." instead of full names |
| `includes/moderation.php` | Daily expiry job; approving a post marks the member as trusted |
| `includes/admin.php` | Admin columns, category filter, Reported view, one-click Approve, pending bubble, meta box, settings page |
| `includes/demo.php` | Demo content and the local-only "View as" bar |

---

## Rebuild the PDF / screenshots

```bash
npm install
```

```bash
npm run pdf -- "Your Name"
```

To re-run the end-to-end check (19 checks across every role) and refresh `docs/img/`, keep `npm start` running in one terminal and run this in another:

```bash
npm run screenshots
```

Both use your installed Microsoft Edge or Chrome. The screenshot run changes demo data (it posts, reports and approves), so run `npm run reset` afterwards for a clean demo.

## Troubleshooting

- **Port 9400 in use:** stop the other process, or change `--port=9400` in `package.json`.
- **`lockWholeFile: unlock failed …` lines on Windows:** harmless Playground log noise; wait for `Ready!`.
- **Blank page or 502 right after starting:** WordPress is still installing. Wait for `Ready!` and refresh.
- **Prototype limits:** payments aren't wired up (PMPro isn't installed in the demo; roles stand in for plans), and emails aren't sent inside Playground.
