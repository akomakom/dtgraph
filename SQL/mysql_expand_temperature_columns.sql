-- At some point in MySQL versions, 4,2 became a bit smaller than what it used to be
-- expand this column - as of now, Mysql 5.1's decimal(4,2) means up to 100
 alter table digitemp modify column Fahrenheit decimal(5,2) not null;
 alter table digtemp_alarms modify column Fahrenheit decimal(5,2) not null;
