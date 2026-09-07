<?php

declare(strict_types=1);

namespace Siel\Acumulus\Whmcs\Helpers;

use RuntimeException;

use Siel\Acumulus\Helpers\Container;

use Siel\Acumulus\Meta;

use function count;
use function is_scalar;

/**
 * Contains wrapper methods around WHMCS's {@see localApi()} call.
 *
 * Also see https://developers.whmcs.com/api/api-index/
 *
 * This class contains some general methods to acess the (local) API:
 * - {@see LocalApi::exec()}
 * - {@see LocalApi::getList()}
 * - {@see LocalApi::get1()}
 *
 * As well as some specialised methods to access specific data, e.g:
 * - {@see LocalApi::getConfig()}
 * - {@see LocalApi::getCurrencyBySuffix()}
 *
 * These specialised methods encapsulate WHMCS specific knowledge about how the responses
 * are formatted and thereby lead to more readable code in the other WHMCS specific
 * classes in this library
 */
class LocalApi
{
    public static function instance(): static
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return Container::getContainer()->getInstance('LocalApi', 'Helpers');
    }

    /**
     * Wrapper around the WHMCS localApi() function that adds some error handling.
     * In case of an error:
     * - an error message is logged.
     * - A runtime exception is thrown.
     *
     * @return array
     *  Array with keys:
     *  - 'result': string: 'success' (or 'error', but that will throw).
     *  - ['message': string: optional, error message in case of error, and will be part
     *     of the exception mesage.]
     *  - Other keys depend on the API function called, see
     *    {@link https://developers.whmcs.com/api/api-index/}.
     *
     * @throws \RuntimeException
     *   Error returned by localApi() call.
     */
    public function exec(string $command, array $values): array
    {
        $results = localAPI($command, $values);
        if ($results['result'] !== 'success') {
            $message = "localApi('$command') failed: {$results['result']}: {$results['message']}";
            throw new RuntimeException($message);
        }
        return $results;
    }

    /**
     * Returns the first (and often only) result of a list request.
     *
     * @param string $plural
     *   The key that contains the list with the "real" results
     * @param string|null $singular
     *   The key within the "real" results list that contains the list.
     *
     * @return array
     *   The (possibly empty) list result of the localApi call.
     *
     * @throws \RuntimeException
     *   Error returned by localApi() call or no list present in the results.
     */
    public function getList(string $command, array $values, string $plural, ?string $singular = null): array
    {
        $results = $this->exec($command, $values);
        $singular ??= substr($plural, 0, -1);
        if (!isset($results[$plural][$singular])) {
            throw new RuntimeException("No $plural\[$singular] list in results");
        }
        return $results[$plural][$singular];
    }

    /**
     * Returns the first (and often only) result of a list request.
     *
     * @param string $plural
     *   The key that contains the list with the "real" results
     * @param string|null $singular
     *   The key within the "real" results list that contains the list.
     *   If absent, $plural without its last character (probably an s) is taken.
     *
     * @return array|null
     *   The (single) result of the localApi call.
     *
     * @throws \RuntimeException
     *  Error returned by localApi() call or list does not contain exactly 1 result.
     *  (The latter condition only if $exactOne = true.)
     */
    public function get1(string $command, array $values, string $plural, ?string $singular = null, bool $exactOne = true): ?array
    {
        $list = $this->getList($command, $values, $plural, $singular);
        if ($exactOne) {
            $count = count($list);
            if ($count !== 1) {
                throw new RuntimeException("List $plural\[$singular] contains $count results");
            }
        }
        return reset($list);
    }

    /**
     * Returns the value for the given setting.
     */
    public function getConfig(string $setting): string
    {
        $results = $this->exec('GetConfigurationValue', ['setting' => $setting]);
        return $results['value'];
    }

    /**
     * Returns whether the product prices include vat or not.
     *
     * Note that at the time that older invoices were created, this setting may have been
     * different. So try to use the invoice subtotal and total to determine if vat is
     * missing on the item lines.
     */
    public function productPricesIncludeTax(): bool
    {
        return ($this->getConfig('TaxType') === 'Inclusive');
    }

    /**
     * Returns the invoice for the given id.
     */
    public function getInvoice(int $id): array
    {
        return $this->exec('GetInvoice', ['invoiceid' => $id]);
    }

    /**
     * Returns the client details for the given user id.
     *
     * Notes:
     * - The old code and Claude seem to suggest that user id and client id are the same.
     * - The result contains the real values under an indirection with key 'client'.
     *   This is unlike e.g. GetInvoice (no indirection) and GetOrders (2 indirections).
     *
     * See https://developers.whmcs.com/api-reference/getclientsdetails/
     */
    public function getClient(int $userId): array
    {
        return $this->exec('GetClientsDetails', ['clientid' => $userId, 'stats' => false])['client'];
    }

    /**
     * Returns a currency for the given suffix.
     *
     * An order only contains the currency prefix and suffix, so if we want currency info,
     * code and rate, we need to look it up via the suffix (hoping that it is unique).
     */
    public function getCurrencyBySuffix(string $currencySuffix): ?array
    {
        $currencySuffix = trim($currencySuffix);
        $currencies = $this->getList('GetCurrencies', [], 'currencies', 'currency');
        foreach ($currencies as $currency) {
            if ($currency['code'] === $currencySuffix) {
                return $currency;
            }
        }
        foreach ($currencies as $currency) {
            if (trim($currency['suffix']) === $currencySuffix) {
                return $currency;
            }
        }
        return null;
    }
}
