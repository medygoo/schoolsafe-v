# Environnement local de reference V1

Ce dispositif est reserve aux tests locaux. Il ne deploie rien sur le VPS.

## Sources et isolation

- Rosario : C:/Users/PC/Documents/rosario, version attendue 12.9.4.
- Sources montees en lecture seule ; configuration de test distincte.
- Base neuve : rosario_test dans codex-ref-v1-db, initialisee depuis rosariosis.sql.
- Aucun port PostgreSQL publie ; reseau Docker interne.
- Rosario rejoint aussi un reseau web pour rendre son port HTTP accessible sur la boucle locale ; son conteneur n'est donc pas isole d'Internet par ce reseau interne.
- Appsmith : instance distincte, volume neuf, image identifiee par digest.
- Secrets generes dans .codex-local-reference-v1 : ne jamais joindre ces fichiers a une livraison.
- Les conteneurs et volumes existants ne sont pas remplaces.

## Adresses prevues

- Rosario local : http://127.0.0.1:18082
- Appsmith local : http://127.0.0.1:18083
- Le port 18081 utilise precedemment est un tunnel vers le VPS, PAS cette instance locale.

## Demarrage initial

Depuis le workspace Codex : node docs/codex-ui-v1/local-reference/start.cjs

Docker doit fonctionner. La commande refuse de remplacer des conteneurs ou volumes deja presents.
Apres une execution partielle, inspecter les ressources avant toute reprise ; ne pas supprimer leurs donnees automatiquement.

Pour redemarrer les conteneurs deja crees :

```powershell
docker start codex-ref-v1-db codex-ref-v1-rosario codex-ref-v1-appsmith
```

## Limites et prochaines validations

- Creation initiale du compte Appsmith a effectuer par le proprietaire dans cette instance locale.
- Comptes du fichier SQL : exemples de test uniquement ; securiser leurs mots de passe avant toute exposition.
- Aucun raccordement Appsmith/Rosario ou SchoolSafe/Rosario n'est cree par ce demarrage.
- Sessions, permissions, isolation entre ecoles et workflows doivent encore etre testes avec les comptes appropries.
- Sources en lecture seule : les fonctions ecrivant des fichiers doivent recevoir un espace de test dedie avant validation.
- Aucun moteur PDF wkhtmltopdf configure.
- Studio de cartes preserve ; sa liaison a Rosario n'est pas validee par ce dispositif.
- Le controle des migrations SchoolSafe reste bloque par un checksum divergent constate dans le preflight.
- Les tests unitaires passes ne constituent pas des tests metier de bout en bout.
- Pas de production autorisee sur la seule base de ce demarrage.
