# Website configuration

Main configuration is stored here. See [nette-2.0 docs](https://doc.nette.org/en/2.0/configuring#toc-framework-configuration)

Common configuration can be extended for development or production.
Then the file `config.local.neon` is loaded - a file which contains password and thus it is not commited to git.



## config.neon

Example config.neon - see more languages

```yaml
#
# SECURITY WARNING: it is CRITICAL that this file is NOT accessible directly via a web browser!
#
# If you don't protect this file from direct web access, anybody will be able to see your passwords.
# http://nette.org/security-warning
#

common:
	parameters:
	
		# Language settings
		defaultLang: cs  # TODO also hardcoded in code (https://github.com/nette/nette/pull/445)
		langs!:
			cs: Česky
			en: English
		
		# Language specific prefixes/domains
		# If differ only in suffix - the more specific goes first
		langDomains!:

		
		# list or hash of Plugin classes
		plugins:

	
		npress:											
			webTitle: My new nPress website
			adminPassword: secret-goes-here
			analytics:      # google analytics tracking number (like UA-123-1) 
			clicky:
			preferCdnJquery: false
			wysiwyg:
				minHtmlHeading: 3
				contentStyle: /static/wysiwyg-content.css


		database:
			host: localhost
			driver: mysqli
			database: npress
			username: root
			password:
			profiler:
				run: false


production < common:
	parameters:
		database:
			host: localhost
			driver: mysqli
			database: npress
			username: production-user
			password: production-password
	
		npress:
			preferCdnJquery: true
		
		

development < common:
console < common:

```
