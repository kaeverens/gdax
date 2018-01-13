# gdax
a GDAX robot

install Composer and run "composer update" to get the GDAX libraries.

copy config.php.dist to config.php and edit it to your taste. most of the settings are tuned already to some values that appear to work well.

set $activeSell to 0 if you want it to only tell you when it looks like a good time to buy or sell.
set $activeSell to 1 if you want it to actually do the trade.

to run, "php run.php"

---

if you'd like to to run a test, you need to have some data.

create a data directory, and run "php build-historic.php". this can take a while because it is downloading blocks of data in chunks of about 380 minutes at the most. a lot of early data is not available from the GDAX server, so tests involving older data might not be realistic. the GDAX server might also return data that overlaps into earlier or later ranges than requested, so it's a good idea to clean up afterwards. A simple way to do this is "cd data/ ; sort LTC-EUR-historic | uniq > LTC-EUR-historic.2 ; mv LTC-EUR-historic.2 LTC-EUR-historic ; cd ../"

to run the actual test, use "php run-test.php". It will do a brute-force list of tests based on parameters found in the run-test.php file.

to change where the test starts from, change the $data_at variable in run-tests.php. it controls what row of data in data/LTC-EUR-historic the tests start from. it's probably best to start from past row 50000 or so, to make sure the data is pretty consistent (GDAX's historic data is spotty when the range is old).
