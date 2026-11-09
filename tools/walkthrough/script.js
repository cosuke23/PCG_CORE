// Presenter script for the walkthrough deck: one entry per slide, read aloud
// by the trainer. Plain spoken English, short sentences, the exact labels on
// screen. Keys are step ids from capture.js (S02-01 …) or slide keys used by
// build_deck.js (cover, about, …). Keep it in step with capture.js.
module.exports = {
    // ---------------------------------------------------------------- opening
    cover: `Good morning, everyone. Today we'll walk through PCG HRMIS from the very first page a visitor sees all the way to re-enlistment.
Everything you'll see is the real system. We clicked through each step ourselves and took the screenshots as we went, so what's on the slides is exactly what you'll find on screen.
Let's start with what the system is for.`,
    about: `A few ground rules before we begin.
First, every screen is real. Nothing here was mocked up.
Second, the names, serial numbers and emails are sample data from our test copy. No real personnel record appears in this deck.
And third, where something you might expect doesn't exist in the system yet, we say so plainly. You'll see those marked "Not implemented", mostly in the re-enlistment section.`,
    purpose: `So what does PCG HRMIS do?
PCG HRMIS is the human resource gateway of the Coast Guard, and MEPCOM HRMIS is the first command system running on it.
Personnel register online and keep their Statement of Information up to date. Unit checkers review their documents. HR keeps the personnel master and the organization tree. MEPTDI runs training. And re-enlistment staff watch who is reaching the end of their term.
Six jobs, one place.`,
    roles: `Here are the people who use it, and where each one signs in.
Members sign in at the regular login. Unit checkers have their own page, and M1 checkers see every unit.
On the admin side, each role sees only what it needs. The HR admin handles registrations and accounts. Reviewers recommend, approvers decide. The auditor can look but never change anything. The Adjutant records Coast Guard awards and schooling. The re-enlistment admin sees one list and nothing else.
Keep this slide in mind. Whenever a button is missing for someone, it's usually their role.`,
    map: `This is the whole journey on one page.
Across the top is the applicant: register, wait for HR, sign in, complete the SOI and submit it.
In the middle are documents. The member uploads, the unit checker reviews, and anything returned goes back to the member.
At the bottom is HR's own path for adding someone to the personnel master: draft, review, approval, and then the record exists.
We'll follow each path in order.`,
    legend: `Quick guide to reading the step slides.
The gold arrow always points at what you click. Blue dashed boxes are fields to fill in. Green means "this is what success looks like". Yellow is something to watch out for, and red is an error or a blocked action.
On the right you'll find the action in plain words and the result you should expect. The dots at the bottom show how far along you are in that workflow.`,

    // ---------------------------------------------------------------- section openers and overviews
    div2: `Let's begin where everyone begins: the landing pages. First PCG HRMIS, the Coast Guard's front page, then MEPCOM HRMIS, where registration and sign-in live.`,
    div3: `Section three: registering a new member. We'll go through all four steps, see what happens while the account waits for approval, and sign in once HR says yes.`,
    div4: `Now the member's own work: the SOI, the required documents, the 201 files, and training. This is the part most of your people will use every day.`,
    div5: `Time to switch sides. This section is for administrators: signing in with the second step, finding your way around the console, and the personnel database.`,
    div6: `Section six is unit management: adding a unit to the organization tree, editing it, and giving it a document checker.`,
    div7: `Section seven is where applicants are actually managed: approving new registrations, reviewing documents as a unit checker, and handling submitted SOIs.`,
    div8: `Section eight follows HR's own registration path, from draft to an approved personnel record, plus document verification and reports.`,
    div9: `Last workflow: re-enlistment. We'll see who's due, how to filter the list, and what the module does and doesn't do.`,
    divM: `A quick look at phones, since many members will use the system that way.`,
    div10: `Let's wrap up with a summary, the messages people run into most, who does what, a few common questions, and a checklist.`,
    types: `A quick note before the steps. People often ask where you choose an application type.
In this system it's at registration: officer or non-officer. After that, the member's application is really their SOI and their documents.
There's no job or vacancy application in the system. "Applications" here means training applications, which we'll also cover.`,
    stages: `Here are the stages a registration goes through, and who acts at each one.
HR writes it and submits it. A reviewer endorses it or sends it back. An approver approves or rejects it, and rejecting always needs remarks. Once approved, the personnel record and its first assignment are created automatically.
Notice that the author never decides their own registration.`,

    // ---------------------------------------------------------------- landing
    'S02-01': `Here's the front door: PCG HRMIS.
The banner shows the three functional commands, MSSC, MEPCOM and MARSLEC. Up in the header you have the main links: Home, Digital services, Organizational structure, MEPCOM HRMIS and Contact.
Nothing here needs a password. It's the public face of the system.`,
    'S02-02': `Try pointing at one of the commands on the banner.
The panel lights up with a gold frame, the other two dim, and a small link slides up from the bottom. On MEPCOM it says "Open MEPCOM HRMIS". On the other two it takes you to the organizational structure.
It's a quick way in for anyone who knows which command they belong to.`,
    'S02-03': `Scroll down and you reach Digital services: "Find the right service, sooner."
The big blue card is MEPCOM HRMIS, the system that's live today. Next to it is registration, with one button for officers and one for non-officers.
Underneath are the everyday links: sign in, unit checkers, the OSAERT certificate check, and HR administration.`,
    'S02-04': `Further down is the Coast Guard's organizational structure, laid out like the official chart.
National Headquarters is on top. Then come the functional commands, the support commands, the 17 Coast Guard Districts, and the special and technical services.
MEPCOM is highlighted, and its gold button opens MEPCOM HRMIS. That's where we're heading next.`,
    'S02-05': `This is MEPCOM HRMIS, the personnel system of the Marine Environmental Protection Command.
Notice "PCG HRMIS" at the start of the menu. That always takes you back to the main page.
On the right is Sign in, for members who already have an account.`,
    'S02-06': `Choose Services and you land on the same choices, from the MEPCOM side.
New personnel pick Officer or Non-officer here. Members who already have an account use "Sign in to the portal".
Have your serial number handy before you register. You'll need it on the very first step.`,
    'S02-07': `Here's the personnel sign-in page.
Members type their email and password. If you don't have an account yet, look below the form: "No account yet? Register as:" gives you Officer or Non-Officer.
Let's register a new non-officer and see the whole thing from the start.`,

    // ---------------------------------------------------------------- registration
    'S03-01': `The registration form has four short steps: Basic, Service, Unit and Verify.
You can see them along the top. The form remembers what you type as you move between steps, so you won't lose anything going back.`,
    'S03-02': `Step one is who you are.
Pick your rank, then type your serial number, your full name and your email. Use an email you actually check, because that's your username from now on.
Then select Next.`,
    'S03-03': `Step two covers your service dates.
Enter your date of birth and your date of appointment. For non-officers, the end of term of enlistment fills in by itself, three years after appointment.
Select Next.`,
    'S03-04': `Step three is your unit.
Choose your command first. The lists after it, category, unit and sub-unit, fill in based on that choice. So work from left to right.
Select Next.`,
    'S03-05': `The last step is verification.
Set a password and type it again. Then allow the camera and select "Capture selfie".
This photo matters. HR compares it with your records before approving you, so face the camera in good light. In our test you'll see a green pattern. That's the browser's test camera, not a real photo.`,
    'S03-06': `Check your photo. If it's not clear, "Capture again" lets you retake it.
When you're happy with it, select "Submit registration".`,
    'S03-07': `And we're back on the sign-in page, with a message: "Your account is pending admin approval."
The account exists now, but it's waiting for HR.`,
    'S03-08': `What happens if you try to sign in anyway?
The page tells you exactly that: "Your account is waiting for admin approval."
Nothing is wrong. You just need HR to approve you first. We'll do that approval in section seven.`,
    'S03-09': `Fast-forward a little. HR has approved the account.
Now the same email and password work. Type them in and select Sign in.`,
    'S03-10': `And here's the member dashboard.
Under "What would you like to do?" you'll find everything a member needs: update the SOI, upload required documents and 201 files, the training tracks, and printing your SOI.
Let's go through those tasks one at a time.`,

    // ---------------------------------------------------------------- applicant workflow
    'S04-01': `This is the dashboard of a member who's been using the system for a while.
See the yellow box? "Documents returned by the reviewer." The unit checker sent something back, and the comment explains why.
We'll deal with that in a minute. First, the SOI. Select "Update my SOI profile".`,
    'S04-02': `The Statement of Information opens as a summary first, so you can read it like a document.
To change anything, select "Edit SOI".`,
    'S04-03': `The SOI has six steps, listed across the top. Step one is basic information: your name, rank, class, and your birth date and place.
Read it carefully and fix anything that's wrong.`,
    'S04-04': `When the step looks right, select "Next step".
This matters: Next step saves the step before moving on. You don't need a separate save button.`,
    'S04-05': `Steps two to five work the same way: personal information, dependents, education and training, and appointments and duties.
Where a section can have more than one entry, there's an Add button. Fill in what applies to you and select Next step after each one.
You can jump back to any step from the list at the top.`,
    'S04-06': `Step six covers awards and career.
Here's the important part. The button now says "Save and submit SOI". Once you submit, the whole SOI becomes read-only.
Some entries, like Coast Guard awards and schooling, are recorded by the Adjutant. You can see them, but you can't change them.`,
    'S04-07': `Before anything is locked, the system asks: "Is the data you entered correct?"
If you're sure, choose "Yes, submit my SOI". If not, choose "No, return to my SOI" and keep editing.
Take your time here. It's the last chance to change things yourself.`,
    'S04-08': `After submitting, try typing anything and you'll see this message: unable to edit, contact your Adjutant or HR Unit Admin.
That's by design. Corrections now go through them, and we'll see how they do it in section seven.`,
    'S04-09': `Now the returned document. Open "Upload required documents".
Accepted documents stay locked. Only three kinds of box are open: anything the checker returned, anything you never uploaded, and clearances that have expired.
Here the NBI clearance was returned, and the checker's comment is right on the box.`,
    'S04-10': `A common mistake. If you upload an NBI, Ombudsman or Sandiganbayan clearance without the "Valid until" date, the system won't take it.
The date field is flagged. Enter the date printed on the clearance, today or later.`,
    'S04-11': `With the file chosen and the date filled in, select "Save and upload".`,
    'S04-12': `Now the record is back with the unit checker, and while it waits, everything locks. Every box and the upload button too.
That's normal. Once the checker decides, anything they return opens up again.`,
    'S04-13': `201 files work the same way, in their own page.
Choose a file for an open section, here B, Appointment, and select "Upload files". The record goes to the checker and locks while it waits.`,
    'S04-14': `Training next. Open Operational training or Administrative training.
Pick your time in grade: one year, two years, or more than two. The courses for your rank and time in grade appear below.`,
    'S04-15': `Select "View and apply" on a course package, check the details, and select "Apply for this training".
Your application goes to the training office for review.`,

    // ---------------------------------------------------------------- admin access
    'S05-01': `Let's switch to the administrator side.
Admins sign in at the admin login with their email and password.`,
    'S05-02': `Every admin role has a second step, except the re-enlistment admin.
Type the six-digit code. Depending on your account, it comes by email or from an authenticator app. Then select Verify.
Codes expire quickly. If yours did, just ask for a new one.`,
    'S05-03': `This is the administration console.
The sidebar on the left lists every module your role can open, grouped under HRMIS, Statement of Information, Re-enlistment, MEPCOM registrations, Training and so on. A super admin lands on Self-registrations.`,
    'S05-04': `Two shortcuts worth knowing.
"Go to a page" at the top finds any page in the sidebar as you type. You can even press the slash key to jump straight into it. And your account menu on the right is where you sign out.`,
    'S05-05': `The HRMIS overview gives you the personnel picture at a glance.
Click a tile, a bar or a rank and the list below filters with it. "Export to CSV" downloads exactly the people you're looking at, and every export is logged.`,
    'S05-06': `Personnel is the master list.
Search by name or serial number, narrow it by unit, classification and status, then select Filter. Click a name to open the full record.`,

    // ---------------------------------------------------------------- units
    'S06-01': `Now unit management. This part is for the super admin.
Open Organization. On the left is the hierarchy of commands and units. On the right, the form to add one.`,
    'S06-02': `Start by selecting the parent, the command or unit the new one belongs under. Here that's MEPCOM.
Then select "New unit" to open the form.`,
    'S06-03': `Fill in the details. Check the parent unit, then type the code and the name, choose the level, and set the "Effective from" date.
The rest is optional: command group, an end date, display order and a description or reference.`,
    'S06-04': `Select "Add unit".
The new unit is created, and the change is written to the audit log.`,
    'S06-05': `There's the confirmation. And in the tree, our new unit, MEPSAM, now sits under MEPCOM.`,
    'S06-06': `To change a unit, select it in the tree and choose "Edit details". Change what you need and select "Save changes".
History isn't overwritten. The audit log keeps every change.`,
    'S06-07': `Units are never deleted. Instead, you deactivate them.
When you select "Deactivate unit", the system asks you to confirm and reminds you that the unit stays in the records and can be activated again. Cancel leaves it as it was.`,
    'S06-08': `To see who belongs to a unit, go to Personnel and choose it in "Unit (incl. subordinate units)", then select Filter.
You get that unit and everything under it.`,
    'S06-09': `Last step for a new unit: give it a checker.
In Unit accounts, fill in the name, email, unit and password, and select "Create account". That person can now sign in as the unit's document checker.
If someone needs to check every unit, "Grant M1 access" does that.`,

    // ---------------------------------------------------------------- applicant management
    'S07-01': `Remember our new registrant? Here's where HR approves them.
Open Self-registrations and select "Review … pending". The table filters to the people who are waiting.`,
    'S07-02': `Find the person and select Review. Double-clicking the row works too.`,
    'S07-03': `HR sees the photo from registration next to the serial number, rank, unit and email.
Compare them with what you know about the person. If it all matches, select Approve. If not, Reject.`,
    'S07-04': `"Approved! The member can now sign in."
That's the moment our registrant from section three could get in.`,
    'S07-05': `Now the unit checker's side. Checkers sign in at the unit login with their own account.`,
    'S07-06': `The headline tells you right away how many records are waiting.
Use the filters, Waiting, Returned, Approved or All, or search by name or serial. Then select Review on a record.`,
    'S07-07': `The review window lists every document on the left. Select one and it opens on the right.
"Open in a new tab" shows it full size if you need a closer look.`,
    'S07-08': `If a document needs to be uploaded again, tick Return on it.
But you can't send it back without saying why. The comment box is flagged until you write something. The member needs to know what to fix.`,
    'S07-09': `Write a clear comment, something like "The NBI clearance is blurred, upload a clear scan", then select "Return 1 item".`,
    'S07-10': `The record now shows Returned, and the member sees your comment on their dashboard. That's the yellow box we saw back in section four.`,
    'S07-11': `When everything in a record is correct, select "Approve record".
Approved documents lock for the member, so nothing changes after you've signed off.`,
    'S07-12': `201 files are reviewed the same way, under "201 file review" in the sidebar.
Same queue, same review window, same approve or return.`,
    'S07-13': `Remember the locked SOI? This is where it gets corrected.
SOI submissions lists every submitted SOI. The Adjutant or HR admin has two choices: "Edit SOI" to fix it themselves, or Reopen, with a reason, so the member can fix it.`,
    'S07-14': `Reopening asks for confirmation. The member will have to submit again, and your reason goes in the audit log.
Select "Reopen SOI", or Cancel.`,
    'S07-15': `"Edit SOI" opens the member's own six steps inside the admin console.
Each Next step saves that step, and the last one says "Save changes". The SOI stays submitted for the member, and every save is logged.`,

    // ---------------------------------------------------------------- processing
    'S08-01': `Section eight is how HR adds personnel directly to the master list.
Open Registrations. The tabs show each stage: Draft, Submitted, Returned for correction, Approved and Rejected. Select "New registration".`,
    'S08-02': `Fill in the identity part: serial number, rank, last name and first name. The optional fields can stay empty.`,
    'S08-03': `Then the proposed assignment: the unit and the effective date.
Select "Save and submit", or "Save draft" if you want to finish later. If someone with a similar name already exists, the system asks you to confirm you checked.`,
    'S08-04': `The registration is now Submitted. The History card on the left records every step.
One thing to note: the person who wrote it can't approve it. Someone else has to.`,
    'S08-05': `The records reviewer opens it next. They can add remarks and select Endorse, or "Return for correction" to send it back to HR.`,
    'S08-06': `Then the approver. They read the details and the history, and choose Approve or Reject. Rejecting needs remarks.`,
    'S08-07': `Approving asks one more time: "Approve and create the personnel record?"
Select Approve.`,
    'S08-08': `And the personnel record exists, with its first assignment already in place.
The history reads created, submitted, endorsed, approved. Every step, who did it and when.`,
    'S08-09': `201 documents uploaded by HR are verified under "201 documents".
Open the "Pending verification" tab, download a file to check it, and select Verify, or Reject with a remark.`,
    'S08-10': `For reports, open Reports. Choose the report and the unit, add dates if the report needs them, and select "Show report".`,
    'S08-11': `The results appear on the page, and "Export CSV" downloads them.
You only ever get the people inside your own access scope, and every export is logged.`,

    // ---------------------------------------------------------------- re-enlistment
    reenlistment: `Before we look at re-enlistment, here's what the module does, and what it doesn't.
It lists non-officers whose end of term of enlistment, or ETE, is coming up. It shows each person's details and whether their re-enlistment form, which is their SOI, is in. And it exports the list.
What it doesn't do is open a re-enlistment case, approve one, or record a decision. Those steps happen outside the system for now, and we've marked them "Not implemented".`,
    'S09-01': `The re-enlistment admin has their own sign-in page.
This role uses the password only. There's no second step.`,
    'S09-02': `The ETE watchlist opens straight away.
The tiles count people whose ETE falls within three, six and twelve months, plus anyone whose ETE has already passed. Start with the three-month tile. Those are the people to act on now.`,
    'S09-03': `Narrow the list with "ETE within", "Command / unit", Account and Search, then select Apply.`,
    'S09-04': `Click a name to open that person's page.`,
    'S09-05': `Here you see the term of enlistment, with the date and the days left, and the re-enlistment form status. "Submitted" means the member has saved their SOI.
Print gives you a paper copy of this page.`,
    'S09-06': `Finally, "Export CSV" downloads the list exactly as you filtered it. Every view and every export is logged.`,

    // ---------------------------------------------------------------- phones + closing
    phones: `Everything we've shown works on a phone too.
Menus fold into a Menu button, forms stack into a single column, and wide tables scroll sideways inside their own frame, so the page itself never does.`,
    summary: `Here's the whole workflow again, with the slides for each part.
If you're training a particular group, you can jump straight to their rows. Applicants, checkers, HR and re-enlistment staff each have their own section.`,
    errors: `These are the messages people actually run into, and what to do about each one.
Most of them aren't errors at all. The system is waiting on someone else: HR approval, a checker's review, or a missing date.
If someone's sidebar is missing a page, check their role with the super admin.`,
    matrix: `And here's who does what, side by side.
Notice the pattern: the people who prepare something are never the ones who approve it. That's deliberate, and it's why every decision has a clear owner.`,
    faq: `A few questions that come up every time.
The short version: pending means wait for HR, locked means ask your Adjutant, and accepted documents stay accepted.`,
    checklist: `Before you finish, run through this checklist for your role.
Applicants: account approved, SOI submitted, documents uploaded, returns answered. Checkers: empty queue, a comment on every return. Admins: pending items decided, units in the right place, each unit with a checker.`,
    closing: `That's PCG HRMIS from end to end.
If something on screen doesn't match these slides, the system has changed and the deck can be rebuilt from the live screens in a few minutes.
Thank you. Let's take your questions.`,
};
