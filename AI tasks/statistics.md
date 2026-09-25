# Task description
Implement statistics collection for admin and user (merchant).

## Statistics for user (merchant)
Implements statistics overview by terminals:
Payments amount by days, months, years
Average bill (check) by day, month, year

## Statistics for admin
It's necessary to have a possibility to get statistics, which terminal/user gives which amount of income to our
payment system by days, months, years. Let's presume that 10% of all successful payments - our income.

## Preferable implementation
It's desirable to have a possibility to broaden the periods of time easily in the future. I think it
would be suitable to use the Strategy pattern.
It's necessary to fill in empty periods (days, months, years without payments). In order not to complicate SQL,
we will make a filling of empty periods on the PHP side.
For calls to the database, it's better to use Laravel Query Builder than ordinary SQL - requests.
