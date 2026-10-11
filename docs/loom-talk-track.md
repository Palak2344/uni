# UniHive – Loom talk track (about 4 minutes)

Before you record, open four tabs: the Wix UniHive page, YMGrad Discussions, the PDF, and the demo (`npm start` → http://127.0.0.1:9400/unihive/).
Talk in your own words; the bullets are prompts, not a script to read.

---

**0:00 – 0:30 · What UniHive is** *(Wix → Our Story, then UniHive page)*
- Unigator's mission: every student deserves a guide. The founder's story is about having no network abroad.
- UniHive is the network part: members helping members with **a place to live, a work visa, a job, part-time work**.
- It sits in the Student Life and Post Graduation stages of the journey.

**0:30 – 1:10 · Who can view, who can post, and the four categories** *(PDF section 1)*
- View and post: **Pro and Premium only**. Visitors and Free members see a teaser with live counts and an upgrade button.
- Why: the posts are the paid value, and they contain students' phone numbers and emails.
- The four categories are the practical, peer-to-peer needs. Part-time Jobs is separate from Jobs because the audience, urgency and visa hour limits are different.
- Note: the Wix page shows "Events and activities". I'd move that to the existing Events pages and use Part-time Jobs, as in the brief.

**1:10 – 1:50 · YMGrad vs UniHive** *(YMGrad tab, scroll the feed)*
- YMGrad: one open feed, no categories, public, likes and comments. Replies like "Available, I can help" are how agents fish for clients.
- YMGrad is an agency, so its forum is lead generation. Unigator is a membership platform, so UniHive is **what members pay for**.
- So UniHive is **structured, moderated, time-limited and has no agents**. I'd borrow YMGrad's easy posting and mobile cards.

**1:50 – 3:20 · How I'd build it: show the prototype** *(demo tab, use the "View as" bar)*
- Approach: **custom post type + taxonomy + custom fields**, plus **Paid Memberships Pro (free)** for plans. A post is a listing, not a thread, so forum plugins are the wrong shape.
- *Visitor* → teaser. Open a post URL → redirected. Nothing leaks through search, REST or feeds.
- *Pro member* → tabs filter by category, search, open a post → contact box, safety tip, Report.
- *New Pro member* → Create a post: title, category, description, contact method. Submitting empty shows validation; a valid post goes to **Awaiting approval** because it's a first post.
- *Moderator* → wp-admin shows only UniHive: pending count, Reported view, one-click **Approve**. After approval the member is trusted.
- *Admin* → Settings: approval mode, 30-day expiry, 3 posts/day, 3-report auto-hide, PMPro levels.

**3:20 – 4:00 · Risks and how I'd handle them** *(PDF section 4)*
- **Scams and agents**: paid-only posting, first-post approval, report → auto-hide, safety tips, no-agents rule (immigration advice is regulated).
- **Privacy**: contact details for members only, short author names, `noindex`, expiry.
- **Moderation load**: only first posts are reviewed; reports and expiry do the rest; a UniHive-only moderator role for ambassadors.
- **Performance**: indexed taxonomy queries and pagination; never cache member pages.
- **Launch**: seed 20–30 real posts so the teaser counts aren't zero.
- Close with the open questions: Premium extras? Replies in v1? Razorpay?
