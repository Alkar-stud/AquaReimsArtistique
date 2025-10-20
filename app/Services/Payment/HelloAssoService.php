<?php

namespace app\Services\Payment;

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
        curl_close($curl);

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
        curl_setopt($ch, CURLOPT_URL, $_ENV['HELLOASSO_API_URL'] . '' . $this->urlApi . $_ENV['HELLOASSO_API_ORGANIZATION_ID'] . $this->urlCheckoutIntents);
        curl_setopt($ch, CURLOPT_POST, 1);// set post data to true
        curl_setopt($ch, CURLOPT_POSTFIELDS,$JsonData);   // post data
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "authorization: Bearer " . $accessToken,
            "content-type:application/json"
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $rawResponse = curl_exec($ch);
        curl_close ($ch);

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
    function checkPayment ($accessToken, $checkoutID): mixed
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $_ENV['HELLOASSO_API_URL'] . '' . $this->urlApi . $_ENV['HELLOASSO_API_ORGANIZATION_ID'] . $this->urlCheckoutIntents . '/' . $checkoutID);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "authorization: Bearer " . $accessToken,
            "content-type:application/json"
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $rawResponse = curl_exec($ch);
        curl_close ($ch);

        // returned json string
        return json_decode($rawResponse);
    }

    /**
     * URL pour l'attestation de paiement
     *
     * @param
     * @return string
     */
    public function recupPaiementAttestation($orderId): string
    {
        return $_ENV['HELLOASSO_API_URL'] . 'associations/' . $_ENV['HELLOASSO_API_ORGANIZATION_ID'] . $this->urlPaymentAttestation . '/' . $orderId;
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
        curl_close ($ch);

        // returned json string
        return json_decode($rawResponse);
    }


}