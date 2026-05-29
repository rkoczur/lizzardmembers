# NEW FEATURE

Summary: Create future tours with details, which the members can book/request place. Admin can create the tour details in the admin side. Members can see the future tours under "Túrák" menu in a new tab. The tours always have a maximum number of attendees, and if a member book a place, the remaining places decrases accordingly. If all places has been taken, members can apply for wait list.

## Admin side
### Tour list
- New tab under "Túrák" named "Meghirdetett Túrák"
- The current tour list should go under tab named "Túranapló"
- In "Meghirdetett túrák" there would be a list of all future tours created and a button for "Új Esemény"
- The list should display: Tour name, maximum and current attendees, date, country, number of days, *Button* for display tour details

### Create new tour
- Fields for new tour: Name, Description, Starting date, maximum attendees, country, tájegység
- Feature to add days with details: tour type (gyalogtúra, vizitúra...etc), km, elevation, description
- Possibility to add fields for the "jelentkezés" űrlap: field name, type

### Display tour
- See all details, and possibility to modify them
- See applied members on the right side in a separate tile
- Can delete applied members from the list
- Display a red notification icon next to the applied members who didn't payed yet and the date when they applied
- Possibility to export all attendees with details (member details and the answers of the jelentkezés) into a csv

## User side
### Tour list
- New tab under "Túrák" named "Meghirdetett Túrák"
- The current tour list should go under tab named "Túranapló"
- In "Meghirdetett túrák" there would be a list of all the new tours
- The list should display: Tour name, maximum and current attendees, date, country, number of days, *Button* for Apply/Details for the tour.
- If click on Apply the tour details will open.

### Display tour/ tour details
- See all details
- See a big button with the text "Jelentkezés"
- If click on "Jelentkezés" a form should came up with text: "Az alábbi űrlapon jelentkezhetsz a túrára. A jelentkezéssel kijelented, hogy 14 napon belül befizeted a részvételi díjad, ellenkező esetben a rendszer automatikusan feloldja a foglalásodat, és amennyiben van várólistán jelentkező, neki adja tovább."
- The "jelentkezés" popup should also contain the following fields: 

 Field name | Type | notes |
|---|---|
| Tudsz autóval jönni? | yes/now | |
| Ha igen hány hely van melletted? | number | it should come up only if the "tudsz autóval jönni" has "yes" as an answer. It also should have the following description: "Ha már megvan hogy kivel utazol, akkor is a maximum számot írd be, és majd a megjegyzésnél jelezd, hogy ki az utasod |
| Szükség esetén aludnál egy helyen mással? | igen, de csak azonos neművel / igen / nem | |
| Megjegyzések | yes/now | |
| All other fields which added by the admin | | |

### User dashboard
- New tile showing the tours which the member has applied, also with the notification for due payment if not yet payed

## Additional function:

- Email notification to admins if new application received
- Email notification to users after application with details

## Rules
- Always try to fit elements to tiles next to each other (two tile max), if the content size let it
- Keep an eye on responsiveness for mobile friendly display

# Bugfixes

- The member e-mail contains a code snipet: [/lizzardmembers/user/future-tour-detail.php?id=1] - it is not a link, it is a normal text.
- When adding a new field into the form as an admin, add a dropdown as an option with predefined answers

# Additional functions:

- The fee should be discounted based on the member level by the following rules:
| Level | Hungarian label | Discount |
|---|---|---|
| 1 | Újonc | |
| 2 | Közlegény | |
| 3 | Tizedes | |
| 4 | Őrmester | |
| 5 | Hadnagy | -5% |
| 6 | Százados | -5% |
| 7 | Őrnagy | -10% |
| 8 | Alezredes | -10% |
| 9 | Ezredes | -15% |

At the tour details for the users, show the full price, and the discounted price if applicable. If no discount applicable, only show the full price

