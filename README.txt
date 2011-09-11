CLI PHP script to import NJ voter records into a MySQL database.

Normalizes the data into two tables joined by the voter ID.

Additional tables are created to hold an ID code from voter contact
as well as phone numbers gatherd from additional lists.

The use of a normalized multi-table stucture presents minor invonveniences,
but allows the voter list to be re-imported without affecting the other,
potentially manually added, data.
