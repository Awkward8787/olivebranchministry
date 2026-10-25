# Still needed from the church

The public site and the office tools use only facts that were confirmed. The portrait names are resolved. Every other item below is still open, and the site leaves those out until the church confirms them.

- Portrait names. Resolved. The owner confirmed these names from the church's Facebook page: Pastor Jerrell Anderson and Pastor Carmen James-Anderson. The portrait caption is "Pastor Jerrell Anderson & Pastor Carmen James-Anderson".
- A second street address, 1601 2nd Ave SE, Moultrie, appeared in one post and is not confirmed. It is not on the site.
- Photographs other than the official banner and that portrait.
- Descriptions for Men's Ministry, Youth Ministry, and Prayer & Fasting beyond the service times already published.
- An online giving link. The Give page explains in-person and mail gifts. `giving.online_url` in `config.php` is the slot for a future HTTPS link. Empty means the link is not shown.
- The email address that should receive contact and prayer notifications. `mail.notify_to` stays empty until that person is named. Submissions are still stored for staff.
- Who receives gifts recorded in the fund with code `hub` (id 4 in a database created from the first schema). The display name may still be "The Hub". Until the church confirms who receives it, `receiving_org` stays unconfirmed. Those gifts stay on the ledger and are left off church contribution statements. The Hub EIN is 41-5032500. It must not be entered as `statement.ein`.
- The church's own EIN, if a year-end statement should print one. Putting 41-5032500 in `statement.ein` does not print it.
- Any extra sentence the church wants on contribution statements (`statement.extra`). When no gift records goods or services, the statement already says: "No goods or services were provided other than intangible religious benefits." A gift that records goods or services also prints a good-faith estimate. The statement does not claim that the church itself is a 501(c)(3).
- How long handled contact messages and prayer requests should be kept. Nothing is deleted until `privacy.handled_days` is set to a positive number of days. When it is set, opening `/admin/inbox` deletes handled rows older than that. Leave it unset until the church decides.
- Ministry details, partner descriptions, and anything else that was not in the confirmed facts.
