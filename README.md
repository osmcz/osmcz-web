# osmcz-web

PHP backend pro OpenStreetMap.cz postavený na [nPress cms](https://github.com/zbycz/npress). 
JS mapová appka je v repozitáři [osmcz-app](https://github.com/osmcz/osmcz-app). 

* **LIVE verze:** [openstreetmap.cz](https://openstreetmap.cz/) 
* **ISSUES:** [na githubu osmcz-app](https://github.com/osmcz/osmcz/issues?q=is%3Aopen+is%3Aissue+label%3Aosmcz-web)
* **DEMO:** [devosm.zby.cz](https://devosm.zby.cz/) - auto-deploy z větve `devosm`


## Jak přispět do projektu
* Frontendová část (osmcz-app) viz. [osmcz/osmcz](https://github.com/osmcz/osmcz)
* Zde je website v PHP s redakčním systémem a rozšíření nPressu (složky `data`, `app` a `theme`) - viz compare 
* Aktuální branche:
  * [master](https://github.com/osmcz/osmcz-web) - vývojová větev
  * [devosm](https://github.com/osmcz/osmcz-web/tree/devosm) - auto-deploy na [devosm.zby.cz](https://devosm.zby.cz)
  * [production](https://github.com/osmcz/osmcz-web/tree/production) - manuální deploy na [openstreetmap.cz](https://openstreetmap.cz)  

### Dev Quickstart
Více v npressím [INSTALL.md](INSTALL.md)

1. nainstalovat php5 + mysql
2. naklonovat repo 
3. pokud je třeba, nastavit zápis `chmod 777 data/files/ data/thumbs/ app/log/ app/temp/`   
4. nastavit v `data/config.local.neon` připojení k databázi + naimportovat `data/dump.sql` 
5. spustit ve složce `php -S localhost:8080`
6. otevřít http://localhost:8080 


### Nasátí změn z `osmcz-app`
```bash
rm -r ./theme/
cp -r ../osmcz-app/* ./theme/
git add -a
git commit -m "deployed osmcz-app v0.20\nosmcz/osmcz@8c0f9e413fefe8f3c4361e96a7eb656cd8023b93"
```


## Deploy na ostrou verzi
```
TODO
git tag deploy_20181231
```

## Autor, licence
(c) 2014-2018 [Pavel Zbytovský](https://zby.cz) a další

Pod licencí MIT - volně šiřte, prodávejte, zachovejte uvedení autora.
