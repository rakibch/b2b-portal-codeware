<?php

namespace App\Services\Payment;

use App\Exceptions\BusinessRuleException;
use App\Services\Payment\Contracts\PaymentGatewayInterface;
use Illuminate\Contracts\Container\Container;

/** Resolves configured gateway drivers (config/payments.php). */
class GatewayManager
{
    /** @var array<string, PaymentGatewayInterface> */
    private array $resolved = [];

    public function __construct(private readonly Container $container) {}

    public function driver(?string $name = null): PaymentGatewayInterface
    {
        $name ??= (string) config('payments.default');

        if (isset($this->resolved[$name])) {
            return $this->resolved[$name];
        }

        $config = config("payments.gateways.{$name}");

        if (! is_array($config) || ! isset($config['driver'])) {
            throw BusinessRuleException::make('gateway_unavailable');
        }

        $driver = $this->container->make($config['driver'], ['config' => $config]);

        if (! $driver instanceof PaymentGatewayInterface) {
            throw new \LogicException("Gateway [{$name}] must implement PaymentGatewayInterface.");
        }

        return $this->resolved[$name] = $driver;
    }

    public function defaultEnabled(): bool
    {
        try {
            return $this->driver()->isEnabled();
        } catch (BusinessRuleException) {
            return false;
        }
    }

    public function has(string $name): bool
    {
        return is_array(config("payments.gateways.{$name}"));
    }
}
