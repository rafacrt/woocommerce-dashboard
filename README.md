# WooCommerce Dashboard

Plugin WordPress que substitui todos os widgets padrão do Dashboard por um painel completo e visual com dados do WooCommerce.

## Funcionalidades

### KPIs em tempo real
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

## Requisitos

| Requisito | Versão mínima |
|---|---|
| WordPress | 6.0 |
| WooCommerce | 7.0 |
| PHP | 7.4 |

## Instalação

1. Copie a pasta `woocommerce-dashboard` para `wp-content/plugins/`
2. Acesse **Plugins → Plugins instalados** no painel do WordPress
3. Ative **WooCommerce Dashboard**
4. Acesse **Painel → Painel** — o dashboard padrão será substituído automaticamente

## Estrutura de arquivos

```
woocommerce-dashboard/
├── woocommerce-dashboard.php   # Registro do plugin
├── includes/
│   ├── class-data.php          # Todas as consultas ao banco (WooCommerce)
│   └── class-dashboard.php     # Registro e renderização dos widgets
├── assets/
│   ├── css/dashboard.css       # Estilos do painel
│   └── js/dashboard.js        # Gráficos via Chart.js 4
└── README.md
```

## Dependências externas

- [Chart.js 4.4.2](https://www.chartjs.org/) — carregado via CDN do jsDelivr

## Observações

- O plugin remove **todos** os widgets padrão do Dashboard (Atividade, Rascunhos, Eventos WordPress etc.) e exibe apenas o painel WooCommerce.
- Todas as consultas usam `$wpdb->prepare()` para evitar SQL injection.
- Compatível com pedidos no formato HPOS (High-Performance Order Storage) do WooCommerce 7+.

## Licença

GPL-2.0+
