Greenwood Wildlife Zoo Management System

Team 9, COSC 3380. Staff use login.html to manage animals, employees, tickets, and retail. Customers use customer-login.html and signup.html to buy tickets, use the gift shop and restaurant, and checkout with a cart.

What is in this repo

webapp has all the PHP pages, HTML entry points, CSS, and images. That folder is what you deploy or serve locally.

sql has trigger_shop_stock_alert.sql for optional gift shop stock alerts. Read it before running on your database.

The .github/workflows folder has the Azure deploy action for pushes to main if your repo still has the secrets set up.

How to install and run

You need PHP 8 with PDO MySQL enabled, MySQL 8, and a browser. You do not need Node or Composer for a basic run.

Get the project files on your machine.

Create or restore the MySQL database using whatever schema or SQL dump your course or team provides. This repo does not include a full schema file in sql, only the trigger script.

Edit the file webapp/db.php with your host, database name, username, and password. If you run MySQL locally without Azure SSL, you may need to change the PDO options until the connection works.

Optional: run the file sql/trigger_shop_stock_alert.sql in MySQL if your schema matches and you want those alerts.

Open a terminal, go into the webapp folder, and run:

cd webapp
php -S localhost:8080

Open http://localhost:8080/index.html for the main site, http://localhost:8080/login.html for staff login, or http://localhost:8080/customer-login.html for customer login.

First admin user for local testing: after the database works, visit create-admin.php once in the browser, then log in with the username and password defined in that file (change them after). create_test_users.php can add more demo staff if you use it the same way.

Tech used

PHP, MySQL, PDO, plain HTML forms, some JavaScript. Hosted demo was on Azure App Service with Azure MySQL.

Deployed site (may change over time)

https://team9zooproject-ctfjc8b7fzhcggeh.eastus2-01.azurewebsites.net/webapp/index.html

If something fails

Check db.php first for wrong host, user, password, or SSL settings. Missing tables usually means the schema was not imported. Wrong dashboard after login usually means the role in the database does not match what dashboard.php expects.

Main map of the app

dashboard.php is the best starting point to see which roles see which tiles. Most pages include session_bootstrap.php at the top. db.php is the only database connection file used everywhere.
