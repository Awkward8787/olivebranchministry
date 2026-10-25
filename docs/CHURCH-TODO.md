# Still needed from the church

The public site and the office tools use only facts that were confirmed. These items are still open. Until they are confirmed, the site leaves them out.

- Portrait names. The leadership page shows the couple photograph with the caption "Our Pastors". It does not name the people in the picture.
- A second street address, 1601 2nd Ave SE, Moultrie, appeared in one post and is not confirmed. It is not on the site.
- Photographs other than the official banner and that portrait.
- Descriptions for Men's Ministry, Youth Ministry, and Prayer & Fasting beyond the service times already published.
- An online giving link. The Give page explains in-person and mail gifts. `giving.online_url` in `config.php` is the slot for a future HTTPS link. Empty means the link is not shown.
- The email address that should receive contact and prayer notifications. `mail.notify_to` stays empty until that person is named. Submissions are still stored for staff.
- Whether gifts recorded in the fund named "The Hub" are received by Olive Branch Ministries Inc. or by The Hub at Olive Branch Inc. Those are separate organizations.
- The church's own EIN, if a year-end statement should print one. The Hub's EIN is 41-5032500 and belongs to The Hub at Olive Branch Inc. It is printed on The Hub page only. Putting it in `statement.ein` does not print it on church statements.
- Any extra sentence the church wants on contribution statements (`statement.extra`). The statement already says that no goods or services were provided unless a gift records that something was provided. It does not claim that the church itself is a 501(c)(3).
- Ministry details, partner descriptions, and anything else that was not in the confirmed facts.
