# [3.2.0](https://github.com/monicahq/laravel-cloudflare/compare/3.1.0...3.2.0) (2022-01-16)


### Features

* add a customisable callback to get proxies ([#234](https://github.com/monicahq/laravel-cloudflare/issues/234)) ([207609b](https://github.com/monicahq/laravel-cloudflare/commit/207609b319f6356ecb0d6c8ded94054b5c6ce77a))

# [3.1.0](https://github.com/monicahq/laravel-cloudflare/compare/3.0.0...3.1.0) (2022-01-15)


### Features

* first time load blocks when not loaded ([#232](https://github.com/monicahq/laravel-cloudflare/issues/232)) ([e63ba67](https://github.com/monicahq/laravel-cloudflare/commit/e63ba6764806e5532144ac98c0b50b57ccdb0120))

# [3.0.0](https://github.com/monicahq/laravel-cloudflare/compare/2.0.0...3.0.0) (2022-01-14)


### Features

* prepare to Laravel 9 and remove Fideloper\Proxy dependency ([#229](https://github.com/monicahq/laravel-cloudflare/issues/229)) ([fd43401](https://github.com/monicahq/laravel-cloudflare/commit/fd434012b83876a00ac1ad92d48560e59a9060e4))


### BREAKING CHANGES

* TrustProxy class has changed extend class

# [2.0.0](https://github.com/monicahq/laravel-cloudflare/compare/1.8.0...2.0.0) (2021-07-14)


### Features

* use laravel Http factory and remove guzzle dependency ([#220](https://github.com/monicahq/laravel-cloudflare/issues/220)) ([34eb0d7](https://github.com/monicahq/laravel-cloudflare/commit/34eb0d77ece88230c74ddaa7db025a8859c8c5d3))


### BREAKING CHANGES

* CloudflareProxies constructor has changed, this should not have an impact if you don't extend it. This version also removes Illuminate < 6.x compatibility.

# [1.8.0](https://github.com/monicahq/laravel-cloudflare/compare/1.7.0...1.8.0) (2021-02-13)


### Features

* Support PHP Version to 8.0 ([#208](https://github.com/monicahq/laravel-cloudflare/issues/208)) ([3ccdec9](https://github.com/monicahq/laravel-cloudflare/commit/3ccdec99a9431de638653af4f693efef12dbe5f0))

## 1.7.0 - 2020-09-09
 ### Enhancements:
  * Support Laravel 8
  * Use fideloper/proxy version ^4.4
  * Allow guzzlehttp/guzzle version ^7.0

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
