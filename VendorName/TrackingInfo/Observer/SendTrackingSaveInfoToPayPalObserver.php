<?php

namespace VendorName\TrackingInfo\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Magento\Sales\Api\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;
use VendorName\TrackingInfo\Model\Config;
use Magento\Sales\Model\Order\Shipment;
use Magento\Sales\Model\ResourceModel\Order\Shipment\Track\CollectionFactory as TrackCollectionFactory;

class SendTrackingSaveInfoToPayPalObserver implements ObserverInterface
{
    protected $orderRepository;
    protected $logger;
    protected $config;

    /** @var  \Magento\Sales\Model\ResourceModel\Order\Shipment\Track\Collection */
    protected $trackingCollection;

    public function __construct(
        OrderRepositoryInterface $orderRepository,
        LoggerInterface $logger,
        Config $config,
        TrackCollectionFactory $collectionFactory

    ) {
        $this->orderRepository = $orderRepository;
        $this->logger = $logger;
        $this->config = $config;
        $this->trackingCollection = $collectionFactory->create();
    }

    public function execute(Observer $observer)
    {
        try {

            $shipment = $observer->getEvent()->getShipment();

            $event = $observer->getEvent();
            $track = $event->getTrack();
            $shipment = $track->getShipment();
            $orderId = $shipment->getOrderId();

            $tracksCollection = $shipment->getTracksCollection();
            foreach ($tracksCollection->getItems() as $track) {
                $trackNumber = $track->getTrackNumber();
                $carrierName = $track->getTitle();
            }

            // Call the method to send tracking info to PayPal
            $this->sendTrackingInfoToPayPal($orderId, $trackNumber, $carrierName);
        } catch (\Exception $e) {
            // Handle exceptions
        }
    }

    public function sendTrackingInfoToPayPal($orderId, $trackNumber, $carrierName)
    {
        $order = $this->orderRepository->get($orderId);

        $getLastTransId = $order->getPayment()->getLastTransId();

        $content = [
            "trackers" => [[
                "transaction_id" => $getLastTransId,
                "tracking_number" => $trackNumber,
                "status" => "SHIPPED",
                "carrier" => $carrierName
            ]]
        ];

        $ppClientId = $this->config->getClientId();
        $ppSecret = $this->config->getClientSecret();

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api-m.sandbox.paypal.com/v1/oauth2/token');
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $ppClientId . ':' . $ppSecret);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(array(
            'grant_type' => 'client_credentials',
        )));

        $resAuthCode = curl_exec($ch);

        if (empty($resAuthCode)) {
            throw new \Exception('Unable to login');
        } else {
            $jsonAuthCode = json_decode($resAuthCode, true);
            $accessToken = $jsonAuthCode['access_token'];

            if (empty($accessToken)) {
                throw new \Exception('No Token Provided');
            } else {
                curl_setopt($ch, CURLOPT_URL, 'https://api-m.sandbox.paypal.com/v1/shipping/trackers-batch');
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($content));
                curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                    'authorization: Bearer ' . $accessToken,
                    'content-type: application/json'
                ));

                $resTrackInfo = curl_exec($ch);

                dump($resTrackInfo); die();

                if (empty($resTrackInfo)) {
                    throw new \Exception('Unable to post shipping data');
                } else {
                    $resTrackInfo = json_decode($resTrackInfo);
                };
            }
        }
        curl_close($ch);
    }
}
