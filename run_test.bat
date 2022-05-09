@echo off
rem php vendor/nette/tester/src/tester.php -c test.ini tests
echo
vendor\bin\phpstan.bat analyse -c phpstan/phpstan.neon app
