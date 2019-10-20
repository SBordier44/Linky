![Enedis Linky](images/linky-enedis-logo.png)
# PHP Linky

Cet outil vous servira à extraire vos données de consommation Linky depuis votre compte Enedis.

### Pré-requis
- Un compteur Linky !
- Un compte Enedis que vous pourrez créer [ici](https://espace-client-particuliers.enedis.fr/web/espace-particuliers/accueil) si ce n'est pas déjà fait.
- *__Attention:__ Vous devez attendre quelques semaines après l'installation du compteur Linky pour voir vos données sur le site Enedis. Une fois ces données disponible, vous pourez cet outil.
- PHP >= 7.3
- Un accès à internet _(pour l'accès à vos données Enedis)_

### Installation
```bash
composer require nuboxdevcom/linky
```


#### Exemple d'utilisation
```php
$linky = new \NDC\Linky\Linky('monemail@monfail.fr', 'monmotdepasse'); // Vos identifiants Enedis
print_r($link->getDataPerYears());
```


#### Fonctions disponibles
- __getDataPerYears()__ => Récupère les consommations annuelles
- __getDataPerMonths('2019')__ => Récupère les conmsommations mensuelles pour l'année 2019
- __getDataPerDays('10', '2019')__ => Récupère les consommations hebdomadaires pour le mois d'octobre 2019
- __getDataPerHours('01', '10', '2019')__ => Récupère les consommations horaires pour une journée donnée


#### Contributions
Ce repository est ouvert à toutes suggestion d'amélioration et contributions ! Les PR sont donc les bienvenus :)
