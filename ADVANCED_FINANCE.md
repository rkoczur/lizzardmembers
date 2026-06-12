# FINANCIAL "BOOKKEEPING" LIKE FUNCTIONALITY

## DESCRIPTION
The purpose of this enhancement is to keep tracking all the expenses and incomes in the association. Currently we use an excel table. In the long run the functionality would merge with the current "Pénzügyek" function and page.

## FEATURES

### Transaction log in admin side
- Completely new feature
- Admin can log every single transaction in a table-like form
- The transactions contain the following details: date, income/expense, category, description, event, partner, amount, account, invoce number
- In a separate tab: Predefined values - here admin can add predefined values into the category, amount and partner which will be used in the same fields of the transactions
- Possibility to remove saved transactions
- Add a new system log into the settings/logs page which tracks every transaction adding, deleting, modifying and the user who made it.

**Defining fields:**
Date: date/time *
Income/expense: selection between income or expense *
Category: selection from the category list (-> predefined values) *
Description: free text *
Event: Selection from the future or previous tours
Partner: Selection from the partner list (-> predefined values) *
Amount: number *
Account: Selection from the account list (-> predefined values) *
Invoice number: free text

### Financial summary on public page
- Keep the original Pénzügyek page and admin side for now, don't touch it
- Make a new financial page called (Detailed finances)
- It should look like the original Pénzügyek in terms of design, formatting and content
- It calculates the yearly expenses and incomes by category from the transaction log