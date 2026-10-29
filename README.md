# RAJO Dashboard

Plugin WordPress que substitui todos os widgets padrão do Painel por um painel completo e visual. Funciona em **dois modos**, escolhidos em **Configurações → RAJO Dashboard**:

- **WooCommerce** — vendas, pedidos e estoque da loja
- **WordPress** — conteúdo, rascunhos, mídia, usuários e saúde do site (não precisa do WooCommerce)

Sem escolha salva, o plugin usa o modo WooCommerce quando o WooCommerce está ativo e o modo WordPress quando não está. Se o modo WooCommerce estiver escolhido e o WooCommerce for desativado, o painel WordPress aparece no lugar.

## Modo WooCommerce

### KPIs
- Receita do dia e do mês atual
- Pedidos recebidos hoje
- Pedidos aguardando pagamento e em processamento
- Ticket médio do mês
- Total de clientes cadastrados

### Gráficos (últimos 30 dias)
- **Barras** — volume de pedidos por dia
- **Linha** — receita diária
- **Donut** — distribuição de pedidos por status

### Tabelas e listas
- **Pedidos recentes** — últimos 10 pedidos com status, cliente, valor e link de edição
- **Mais vendidos** — top 5 produtos por quantidade vendida (com miniatura)
- **Estoque baixo** — produtos com 5 ou menos unidades em estoque, destacando os zerados

## Modo WordPress

### KPIs
- Páginas e posts publicados
- Rascunhos (todos os tipos de conteúdo)
- Arquivos de mídia
- Usuários
- Comentários aguardando moderação
- Atualizações disponíveis (WordPress, plugins e temas)

### Gráficos (últimos 30 dias)
- **Barras** — publicações por dia
- **Barras** — itens editados por dia
- **Donut** — mídia por tipo (imagens, vídeos, documentos…)

### Tabelas e listas
- **Conteúdo por tipo** — publicados, rascunhos e privados de cada tipo, inclusive os personalizados do site (descobertos sozinhos)
- **Alterações recentes** — últimos 10 itens editados, com tipo, status e quem editou
- **Rascunhos** — os mais recentes, com link de edição
- **Categorias e termos** — total de termos de cada taxonomia
- **Saúde do site** — versões do WordPress e PHP, tema, plugins ativos, cache de objetos, debug e indexação

## Painéis próprios de cada site

Qualquer plugin, mu-plugin ou tema pode acrescentar painéis abaixo do painel principal, nos dois modos, pelo filtro `rajo_dashboard_paineis`:

```php
add_filter( 'rajo_dashboard_paineis', function ( $paineis, $modo ) {
    $paineis[] = [
        'titulo'  => 'Minhas unidades',
        'link'    => [ admin_url( 'edit.php?post_type=unidade' ), 'Ver todas →' ], // opcional
        'largura' => 'metade', // 'inteira' (padrão) ou 'metade'
        'render'  => function () {
            echo '<p>Conteúdo do painel</p>';
        },
    ];
    return $paineis;
}, 10, 2 );
```

As classes de estilo do painel (`wcd-table`, `wcd-badge`, `wcd-stock-list`, `wcd-empty`…) podem ser usadas dentro do `render`.

## Requisitos

| Requisito | Versão mínima |
|---|---|
| WordPress | 6.0 |
| PHP | 7.4 |
| WooCommerce (só no modo WooCommerce) | 7.0 |

Compatível com pedidos no formato HPOS (tabelas próprias de pedidos do WooCommerce) e no formato antigo (`wp_posts`).

## Instalação

1. Copie a pasta do plugin para `wp-content/plugins/`
2. Acesse **Plugins → Plugins instalados** e ative **RAJO Dashboard**
3. Em **Configurações → RAJO Dashboard**, escolha o modo
4. Acesse **Painel → Início**

### Atualizando a partir do "WooCommerce Dashboard" (1.x)

O arquivo principal mudou de `woocommerce-dashboard.php` para `rajo-dashboard.php`. Depois de trocar os arquivos, o WordPress desativa o plugin antigo: é só ativar **RAJO Dashboard** de novo. O modo WooCommerce é escolhido sozinho quando o WooCommerce está ativo.

## Estrutura de arquivos

```
rajo-dashboard/
├── rajo-dashboard.php           # Registro do plugin
├── includes/
│   ├── class-settings.php       # Configurações → RAJO Dashboard (escolha do modo)
│   ├── class-dashboard.php      # Assets, cabeçalho, painéis extras e escolha da tela
│   ├── class-woo-data.php       # Consultas do modo WooCommerce (HPOS e legado)
│   ├── class-wp-data.php        # Consultas do modo WordPress
│   └── views/
│       ├── woocommerce.php      # Tela do modo WooCommerce
│       └── wordpress.php        # Tela do modo WordPress
├── assets/
│   ├── css/dashboard.css        # Estilos do painel
│   └── js/dashboard.js          # Gráficos via Chart.js 4
└── README.md
```

## Dependências externas

- [Chart.js 4.4.2](https://www.chartjs.org/) — carregado via CDN do jsDelivr

## Observações

- O plugin remove **todos** os widgets padrão do Painel (Atividade, Rascunhos, Eventos WordPress etc.) e exibe apenas o painel do modo escolhido.
- Todas as consultas usam `$wpdb->prepare()` para evitar SQL injection.

## Licença

GPL-2.0+
