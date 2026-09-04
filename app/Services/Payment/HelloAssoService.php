<?php

namespace app\Services\Payment;

use RuntimeException;

class HelloAssoService
{
    protected string $urlToken = 'oauth2/token';
    protected string $urlApi = 'v5/organizations/';
    protected string $urlApiPayment = 'v5/payments/';
    protected string $urlCheckoutIntents = '/checkout-intents';
    protected string $urlPaymentAttestation = '/checkout/paiement-attestation';
    public function __construct()
    {
    }

    /**
     * Pour récupérer le token d'accès à l'API HelloAsso
     *
     * @return string
     */
    public function GetToken(): string
    {
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $_ENV['HELLOASSO_API_URL'] . '' . $this->urlToken,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => 'grant_type=client_credentials&client_id=' . $_ENV['HELLOASSO_API_CLIENT_ID'] . '&client_secret=' . $_ENV['HELLOASSO_API_CLIENT_SECRET'],
            CURLOPT_HTTPHEADER => array(
                'cache-control: no-cache',
                'content-type: application/x-www-form-urlencoded'
            )));

        $json = curl_exec($curl);
        //curl_close($curl);

        // returned json string
        $obj = json_decode($json);

        if (isset($obj->{'access_token'})) {
            $accessToken = $obj->{'access_token'};
        }
        else {
            $httpCode = curl_getinfo($curl);
            echo '<pre>Erreur d\'obtention du token : ';
            print_r($json);
            echo '<hr>Retour curl : ';
            print_r(curl_getinfo($curl));
            echo '</pre>';
            die;
        }

        return $accessToken;
    }

    /**
     * Pour envoyer les data et récupérer l'url de paiement
     *
     * @param $accessToken
     * @param $TabData
     * @return mixed
     */
    public function PostCheckoutIntents($accessToken,$TabData): mixed
    {
        $JsonData = json_encode($TabData);

        $ch = curl_init();
        curl_setopt($ch,
            CURLOPT_URL,
            $_ENV['HELLOASSO_API_URL'] . '' . $this->urlApi . $_ENV['HELLOASSO_API_ORGANIZATION_ID'] . $this->urlCheckoutIntents
        );
        curl_setopt($ch, CURLOPT_POST, 1);// set post data to true
        curl_setopt($ch, CURLOPT_POSTFIELDS,$JsonData);   // post data
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "authorization: Bearer " . $accessToken,
            "content-type:application/json"
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $rawResponse = curl_exec($ch);
        //curl_close ($ch);

        // return json string
        return json_decode($rawResponse);
    }

    /**
     * Pour vérifier auprès de HelloAsso si la commande a bien été payée.
     *
     * @param $accessToken
     * @param $checkoutID
     * @return mixed
     */
    function checkPayment($accessToken, $checkoutID): mixed
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $_ENV['HELLOASSO_API_URL'] . '' . $this->urlApi . $_ENV['HELLOASSO_API_ORGANIZATION_ID'] . $this->urlCheckoutIntents . '/' . $checkoutID);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "authorization: Bearer " . $accessToken,
            "content-type:application/json"
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $rawResponse = curl_exec($ch);
        //curl_close ($ch);

        // returned json string
        return json_decode($rawResponse);
    }

    /**
     * Vérifie l'état d'un paiement
     *
     * @param int $paymentId
     * @return mixed
     */
    public function checkPaymentState(int $paymentId): mixed
    {
        $accessToken = $this->GetToken();
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $_ENV['HELLOASSO_API_URL'] . '' . $this->urlApiPayment . $paymentId);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "authorization: Bearer " . $accessToken,
            "content-type:application/json"
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $rawResponse = curl_exec($ch);
        //curl_close ($ch);

        // returned json string
        return json_decode($rawResponse);
    }

    /**
     * Pour demander le remboursement d'un paiement
     *
     * @param int $paymentId
     * @param string|null $comment
     * @return bool
     */
    public function refundPayment(int $paymentId, ?string $comment = null): mixed
    {
        $accessToken = $this->GetToken();
        if ($comment) {
            $JsonData = json_encode(['comment' => $comment]);
        } else {
            $JsonData = json_encode([]);
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $_ENV['HELLOASSO_API_URL'] . '' . $this->urlApiPayment . $paymentId . '/refund');
        curl_setopt($ch, CURLOPT_POST, 1);// set post data to true
        curl_setopt($ch, CURLOPT_POSTFIELDS,$JsonData);   // post data
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "authorization: Bearer " . $accessToken,
            "content-type:application/json"
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $rawResponse = curl_exec($ch);
        //curl_close ($ch);

        // return json string
        return json_decode($rawResponse);
    }


    /**
     * Récupère une commande HelloAsso.
     *
     * @param int $orderId
     * @return array
     */
    public function GetOrder(int $orderId): array
    {
        if ($orderId <= 0) {
            throw new RuntimeException(
                'Le numéro de commande HelloAsso est invalide.'
            );
        }

        $accessToken = $this->GetToken();

        $apiUrl = rtrim($_ENV['HELLOASSO_API_URL'] ?? '', '/') . '/';

        $url = $apiUrl
            . 'v5/orders/'
            . $orderId
            . '?withFormData=false&checkPaymentsRefundEligibility=false';

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => [
                'accept: application/json',
                'Authorization: Bearer ' . $accessToken,
            ],
        ]);

        $json = curl_exec($curl);
        $curlError = curl_error($curl);
        $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);

        //curl_close($curl);

        if ($json === false || !empty($curlError)) {
            throw new RuntimeException(
                'Erreur lors de la connexion à HelloAsso.'
            );
        }

        /*
         * Une commande inexistante renvoie normalement un code HTTP 404.
         * On utilise une exception spécifique pour permettre au contrôleur
         * d'afficher un message adapté.
         */
        if ($httpCode === 404) {
            throw new RuntimeException(
                'Commande HelloAsso introuvable.',
                404
            );
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new RuntimeException(
                'HelloAsso a retourné une erreur lors de la récupération de la commande.',
                $httpCode
            );
        }

        $order = json_decode($json, true);

        if (!is_array($order)) {
            throw new RuntimeException(
                'La réponse de HelloAsso est invalide.'
            );
        }

        return $order;
    }

    /**
     * Récupère les informations utiles d'une commande.
     * Tous les customFields sont conservés, quel que soit leur nom.
     * Cela permet de supporter automatiquement tous les produits présents dans la boutique HelloAsso.
     * @param array $order
     * @return array
     */
    public function GetUsefulOrderData(array $order): array
    {
        $payer = $order['payer'] ?? [];

        $result = [
            'orderId' => $order['id'] ?? null,

            'payer' => [
                'email' => $payer['email'] ?? '',
                'firstName' => $payer['firstName'] ?? '',
                'lastName' => $payer['lastName'] ?? '',
                'country' => $payer['country'] ?? '',
            ],

            'items' => [],

        ];

        foreach ($order['items'] ?? [] as $item) {

            $customFields = [];

            /*
             * CustomFields directement rattachés à l'article.
             */
            foreach ($item['customFields'] ?? [] as $customField) {

                if (!isset($customField['name'])) {
                    continue;
                }

                $customFields[] = [
                    'id' => $customField['id'] ?? null,
                    'name' => $customField['name'],
                    'type' => $customField['type'] ?? null,
                    'answer' => $customField['answer'] ?? '',
                ];
            }

            /*
             * CustomFields éventuellement présents dans les options
             * de l'article.
             */
            foreach ($item['options'] ?? [] as $option) {

                foreach ($option['customFields'] ?? [] as $customField) {

                    if (!isset($customField['name'])) {
                        continue;
                    }

                    $customFields[] = [
                        'id' => $customField['id'] ?? null,
                        'name' => $customField['name'],
                        'type' => $customField['type'] ?? null,
                        'answer' => $customField['answer'] ?? '',
                        'optionName' => $option['name'] ?? null,
                    ];
                }
            }

            $result['items'][] = [
                'name' => $item['name'] ?? '',
                'id' => $item['id'] ?? null,
                'amount' => $item['amount'] ?? 0,
                'type' => $item['type'] ?? null,
                'state' => $item['state'] ?? null,
                'customFields' => $customFields,
            ];

        }

        return $result;
    }


}