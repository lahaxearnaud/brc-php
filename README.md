# Billion row challenge in PHP

Based on this challenge [https://github.com/gunnarmorling/1brc](https://github.com/gunnarmorling/1brc)

## Create the dataset

```bash
for i in {1..200}; do cat measurements-5000000.txt >> measurements.txt; done;
```

## Build the project

```bash
composer install -o
```

## Run the code

```bash
php challenge.php
```

## Results

Test 1:

    - Intel(R) Core(TM) i7-10610U CPU @ 1.80GHz 
    - OS: Ubuntu 22
    - PHP: PHP 8.1
    - Time: 2.22 m
