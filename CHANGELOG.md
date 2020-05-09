# UNRELEASED CHANGES:

### New features:
*

### Enhancements:
*

### Fixes:
*

# RELEASED VERSIONS:

## 1.6.0 - 2020-05-09
 ### Enhancements:
  * Remove verbosity parameter

## 1.5.0 - 2020-05-08
 ### New features:
  * Merge Cloudflare ips with trusted proxies

## 1.4.0 - 2020-03-07
 ### Enhancements:
  * Support Laravel 7 and higher

## 1.3.0 - 2019-09-26
 ### Enhancements:
  * Support Laravel 6.0

## 1.2.0 - 2019-07-02
 ### Enhancements:
  * Support Laravel 5.5

 ### Fixes:
  * Use laravelcloudflare.cache config value instead of static value


## 1.1.0 - 2019-03-23
 ### New features:
  * Use fideloper/proxy package as a backend to set trusted proxies

 ### Enhancements:
  * Add a config file
  * Improve test coverage

 ### Fixes:
  * Use register() ServiceProvider instead of boot()


## 1.0.1 - 2018-11-03
 ### Fixes:
  * Prefix http kernel middleware entry with slash in readme
  * Fix UnexpectedValueException throw
  * Fix some phpdoc


## 1.0.0 - 2018-10-05
 ### New features:
  * First release of monicahq/cloudflare

 ### Enhancements:
  * Use guzzlehttp/guzzle to query cloudflare ips

