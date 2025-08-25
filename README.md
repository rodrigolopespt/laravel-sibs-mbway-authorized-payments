# 🚀 Laravel SIBS MBWay Authorized Payments

[![Latest Version on Packagist](https://img.shields.io/packagist/v/rodrigolopespt/laravel-sibs-mbway-authorized-payments.svg?style=flat-square)](https://packagist.org/packages/rodrigolopespt/laravel-sibs-mbway-authorized-payments)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/rodrigolopespt/laravel-sibs-mbway-authorized-payments/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/rodrigolopespt/laravel-sibs-mbway-authorized-payments/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/rodrigolopespt/laravel-sibs-mbway-authorized-payments/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/rodrigolopespt/laravel-sibs-mbway-authorized-payments/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/rodrigolopespt/laravel-sibs-mbway-authorized-payments.svg?style=flat-square)](https://packagist.org/packages/rodrigolopespt/laravel-sibs-mbway-authorized-payments)

Package Laravel para integração completa com **Pagamentos Autorizados MBWay** da SIBS Gateway. Permite implementar cobranças automáticas recorrentes após autorização inicial do cliente.

## 🎯 **Core Features - Pagamentos Autorizados**

- **🔐 Autorização Única**: Cliente autoriza uma vez no MB WAY app um valor máximo
- **💰 Cobranças Automáticas**: Processar cobranças recorrentes sem validação adicional
- **📱 Controlo Total**: Cliente gere autorizações diretamente no MB WAY app
- **🔄 Gestão Completa**: Criar, consultar, cancelar e gerir autorizações
- **📊 Eventos & Webhooks**: Sistema de notificações integrado
- **🛡️ Seguro & Robusto**: Validações, retry logic e tratamento de erros

## 📋 **Casos de Uso Perfeitos**

- **📺 Streaming Services**: Netflix, Spotify, Disney+ (cobranças mensais)
- **⚡ Utilities**: Água, luz, gás (faturas mensais variáveis)
- **🛒 E-commerce**: One-click purchases (compras sem validação)
- **💳 SaaS**: Subscrições de software (planos mensais/anuais)
- **🏥 Seguros**: Pagamentos automáticos de seguros

## 🚀 **Como Funciona**

### **1. Autorização Inicial (Uma vez)**
```php
// Cliente solicita subscrição
$authorization = SibsMbwayAP::createAuthorization([
    'customerPhone' => '351919999999',
    'customerEmail' => 'customer@example.com',
    'maxAmount' => 500.00, // €500 limite anual
    'validityDate' => now()->addYear(),
    'description' => 'Netflix Premium - Subscrição',
]);

// Cliente aprova no MB WAY app (fora do nosso controlo)
// Webhook confirma quando autorização fica ativa
```

### **2. Cobranças Automáticas (Sem interação do cliente)**
```php
// Cobrança mensal automática
$charge = SibsMbwayAP::processCharge(
    $authorization, 
    12.99, 
    'Netflix Premium - Janeiro 2024'
);

if ($charge->isSuccessful()) {
    // Ativar serviço por mais um mês
    $user->extendSubscription();
}
```

## 📦 **Instalação**

```bash
composer require rodrigolopespt/laravel-sibs-mbway-authorized-payments
```

Publicar configuração e migrações:

```bash
php artisan vendor:publish --tag="sibs-mbway-authorized-payments-config"
php artisan vendor:publish --tag="sibs-mbway-authorized-payments-migrations"
php artisan migrate
```

## 📱 **Formatos de Telefone Suportados**

O package aceita automaticamente vários formatos de telefone portugueses:

```php
// Todos estes formatos são aceites e convertidos automaticamente:
'customerPhone' => '351919999999',     // Formato limpo
'customerPhone' => '+351919999999',    // Internacional
'customerPhone' => '+351 919 999 999', // Internacional com espaços
'customerPhone' => '351-919-999-999',  // Com hífens
'customerPhone' => '351.919.999.999',  // Com pontos

// Todos são convertidos para: 351919999999
```

## ⚙️ **Configuração**

Adicionar credenciais SIBS ao `.env`:

```bash
# SIBS Configuration
SIBS_ENVIRONMENT=sandbox
SIBS_TERMINAL_ID=your_terminal_id
SIBS_AUTH_TOKEN=your_auth_token
SIBS_CLIENT_ID=your_client_id

# Webhook Configuration  
SIBS_WEBHOOK_URL=https://yourdomain.com/webhooks/sibs
SIBS_WEBHOOK_SECRET=your_webhook_secret

# Optional Settings
SIBS_AUTH_VALIDITY_DAYS=365
SIBS_MAX_AMOUNT=1000
SIBS_AUTO_RETRY=true
SIBS_RETRY_ATTEMPTS=3
```

## 💡 **Uso Básico**

### **Criar Autorização**
```php
use Rodrigolopespt\SibsMbwayAP\Facades\SibsMbwayAP;

$authorization = SibsMbwayAP::createAuthorization([
    'customerPhone' => '351919999999', // Accepts: 351919999999, +351919999999, +351 919 999 999
    'customerEmail' => 'customer@example.com',
    'maxAmount' => 100.00,
    'validityDate' => now()->addYear(),
    'description' => 'Monthly Subscription',
    'merchantReference' => 'SUB_2024_001',
]);
```

### **Processar Cobrança**
```php
$charge = SibsMbwayAP::processCharge(
    $authorization,
    29.99,
    'January 2024 Payment'
);

if ($charge->isSuccessful()) {
    // Lógica de negócio - ativar serviço
}
```

### **Gerir Autorizações**
```php
// Listar autorizações ativas
$active = SibsMbwayAP::listActiveAuthorizations();

// Verificar status
$status = SibsMbwayAP::checkAuthorizationStatus($authId);

// Cancelar autorização
$cancelled = SibsMbwayAP::cancelAuthorization($authId);
```

### **Reembolsos**
```php
// Reembolso total
$refunded = SibsMbwayAP::refundCharge($charge);

// Reembolso parcial
$refunded = SibsMbwayAP::refundCharge($charge, 15.00);
```

## 🔔 **Eventos & Listeners**

O package dispara eventos para integração com a sua aplicação:

```php
// app/Providers/EventServiceProvider.php
use Rodrigolopespt\SibsMbwayAP\Events\{
    AuthorizationCreated,
    ChargeProcessed,
    ChargeFailed,
    AuthorizationExpired
};

protected $listen = [
    AuthorizationCreated::class => [
        ActivateCustomerSubscription::class,
        SendWelcomeEmail::class,
    ],
    
    ChargeProcessed::class => [
        ExtendSubscriptionPeriod::class,
        SendPaymentReceipt::class,
    ],
    
    ChargeFailed::class => [
        NotifyPaymentFailure::class,
        ScheduleRetry::class,
    ],
];
```

## 🔧 **Comandos Úteis**

```bash
# Processar autorizações expiradas
php artisan sibs:process-expired-authorizations

# Retry cobranças falhadas
php artisan sibs:retry-failed-charges

# Limpeza geral (com opções)
php artisan sibs:cleanup-expired --days=90 --dry-run
php artisan sibs:cleanup-expired --force
```

## 🧪 **Testing**

```bash
composer test
```

## 📊 **Models Disponíveis**

### **AuthorizedPayment**
```php
$authorization->isActive(); // Check if authorization is active
$authorization->canChargeAmount(50.00); // Check if can charge amount
$authorization->getTotalChargedAmount(); // Get total charged so far
$authorization->getRemainingAmount(); // Get remaining amount
$authorization->charges; // Get all charges
$authorization->successfulCharges; // Get successful charges only
```

### **Charge**
```php
$charge->isSuccessful(); // Check if charge was successful
$charge->isFailed(); // Check if charge failed
$charge->canBeRefunded(); // Check if charge can be refunded
$charge->getRemainingRefundableAmount(); // Get refundable amount
$charge->authorizedPayment; // Get related authorization
```

## 🛡️ **Tratamento de Erros**

```php
use Rodrigolopespt\SibsMbwayAP\Exceptions\{
    AuthorizationException,
    ChargeException,
    SibsException
};

try {
    $charge = SibsMbwayAP::processCharge($auth, 100.00);
    
} catch (AuthorizationException $e) {
    // Autorização inválida, expirada, etc.
    $context = $e->getContext();
    
} catch (ChargeException $e) {
    // Erro na cobrança (valor excedido, etc.)
    
} catch (SibsException $e) {
    // Erro genérico da API SIBS
}
```

## 🔄 **Webhooks**

O package configura automaticamente a rota de webhook:
- **URL**: `/webhooks/sibs` 
- **Método**: `POST`
- **Validação**: Assinatura HMAC SHA256

Os webhooks processam automaticamente:
- ✅ Aprovação de autorizações
- ❌ Cancelamento de autorizações  
- 💰 Sucesso de cobranças
- ⚠️ Falhas de cobranças

## 📚 **Documentação Completa**

- [**Exemplos de Uso**](docs/usage-examples.md) - Casos práticos detalhados
- [**Plano de Implementação**](docs/implementation_plan.md) - Arquitetura e roadmap

## 🔒 **Segurança**

- Validação de webhooks com HMAC SHA256
- Sanitização de dados sensíveis nos logs
- Validação rigorosa de inputs
- Tratamento seguro de credenciais

## 🤝 **Contribuir**

```bash
git clone https://github.com/rodrigolopespt/laravel-sibs-mbway-authorized-payments
cd laravel-sibs-mbway-authorized-payments
composer install
```

Por favor, veja [CONTRIBUTING](CONTRIBUTING.md) para detalhes.

## 🔄 **Changelog**

Por favor, veja [CHANGELOG](CHANGELOG.md) para mais informações sobre mudanças recentes.

## 📝 **Licença**

MIT License. Por favor, veja [License File](LICENSE.md) para mais informações.

## 🎯 **Créditos**

- [Rodrigo Matas Lopes](https://github.com/rodrigolopespt)
- [Todos os Contribuidores](../../contributors)

---

<p align="center">
<strong>🚀 Simplifique os seus pagamentos recorrentes com MBWay Authorized Payments!</strong><br>
<em>Perfect for SaaS, Streaming, E-commerce & Utilities</em>
</p>
