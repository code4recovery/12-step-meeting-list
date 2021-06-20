---
name: Extend Meeting Detail Feedback System

about: Replace the existing Feedback feature of the Meeting Detail screen (single-meetings.php) with an enhanced New or Change Request input screen. Similar to the old feedback screen, the new/change 
request screens would remain hidden until activated by the user, only then replacing the map view in the right side column of the Detail page. The input fields would be transformed by the user in a  
controled fashion prior to submitting the form data, ensuring that the website administrator receives a full set of accurate and auditable data with which to update the Meeting Information. The submitted 
data would be parsed and injected into an html table within an email, with changes highlighted in Red text for easy analysis and action by the system administrator. 

title: 'Enhance Feedback Requests'
labels: 'new feature/enhancement'
assignees: ''

---

**Is your feature request related to a problem? Please describe.**
Yes. The current Feedback feature relies on the average user to have more knowledge of the Meeting attributes than is reasonable to expect. Mistakes and misunderstandings are common-place which could 
easily be avoided by providing a formatted solution to guide the user input, giving a consistent, auditable, and accurate view of what the Requestor is wanting added or changed to the 12 Step Meeting
List solution. 

**Describe the solution you'd like**
Ideally, all meeting attributes are available in the form of input controls, using radio buttons, checkboxes, and dropdown lists whenever possible. Free form text controls should suffice for the rest,
with all controls being managed with validation mechanisms when possible. Hook into the existing Feedback feature activation mechanism, possibly giving system administrators the choice of overriding 
the simple Feedback form with the more enhanced Change Request form.

**Describe alternatives you've considered**
An alternative could be to incorporate this as a new feature instead of an extension to an existing feature. Possibly create a separate stand-alone plug-in using Popups to configure the Request prior
to submitting.

**Additional context**
Add any other context or screenshots about the feature request here.
See screenshots MeetingDetailScreen1.png, MeetingDetailScreen2.png, MeetingRequestSubmitted.png, and ChangeRequestEmail.png.
Focus above is on the Change Meeting Request, but the feature must include the associated Add New Meeting Request and Remove Meeting screens as well.

