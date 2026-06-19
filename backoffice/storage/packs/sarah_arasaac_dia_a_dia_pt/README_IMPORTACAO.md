# SARAH ARASAAC - Pack Dia a Dia PT v1

Este pacote foi criado para servir como **pack inicial** da PWA SARAH, com categorias e frases mais usadas no dia a dia.

## Conteúdo

- Categorias: 13
- Itens/frases: 120
- Idioma: pt-PT
- Voz: pt-PT

## Ficheiros principais

- `seed.json` — usar como pacote principal.
- `sarah_pack_dia_a_dia_pt.json` — cópia com nome descritivo.
- `startup_pack.json` — cópia para arranque inicial.
- `icons_required.csv` — lista dos SVG esperados na biblioteca.
- `startup_pack_config.js` — exemplo de URL para a PWA.

## Onde colocar no servidor

Sugestão:

```text
/backoffice/storage/packs/sarah_arasaac_dia_a_dia_pt/seed.json
```

E os pictogramas devem existir na biblioteca:

```text
/backoffice/storage/icons/
```

Os caminhos no JSON usam o formato:

```text
icons/comer.svg
icons/agua.svg
icons/casa de banho.svg
```

Isto encaixa com o teu backoffice atual, onde a API devolve caminhos do tipo `icons/comer.svg`.

## Como usar como pacote de abertura da PWA

Na área escondida por triplo clique no menu hambúrguer, define o URL remoto como:

```text
https://sarah.aeaveromar.pt/backoffice/storage/packs/sarah_arasaac_dia_a_dia_pt/seed.json
```

Depois manda atualizar/importar pacote.

## Observação importante

Este pack referencia os pictogramas pelo nome esperado na tua biblioteca ARASAAC. Se algum SVG tiver nome diferente, usa o `icons_required.csv` para comparar e ajustar no editor.
