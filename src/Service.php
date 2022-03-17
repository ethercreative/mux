<?php

namespace ether\mux;

use Craft;
use craft\base\Component;
use craft\db\Query;
use craft\elements\Asset;
use craft\helpers\App;
use MuxPhp\Api\AssetsApi;
use MuxPhp\Configuration;
use MuxPhp\Models\CreateAssetRequest;
use MuxPhp\Models\InputSettings;
use MuxPhp\Models\PlaybackPolicy;

class Service extends Component
{

	public function createMuxFromAsset (Asset $asset)
	{
		$input = new InputSettings([
			'url' => $asset->getUrl(),
		]);

		$request = new CreateAssetRequest([
			'input' => $input,
			'playback_policy' => PlaybackPolicy::_PUBLIC,
		]);
		$response = $this->_api()->createAsset($request);
		$data = $response->getData();

		Craft::$app->getDb()
			->createCommand()
			->insert(Mux::MUX_ASSETS_TABLE, [
			   'assetId' => $asset->id,
			   'muxId' => $data->getId(),
			], false)
			->execute();
	}

	public function deleteMuxFromAsset (Asset $asset)
	{
		$muxId = (new Query())
			->select('muxId')
			->from(Mux::MUX_ASSETS_TABLE)
			->where(['assetId' => $asset->id])
			->scalar();

		if (empty($muxId))
			return;

		$this->_api()->deleteAsset($muxId);
	}

	public function getPlaybackUrl (Asset $asset):? string
	{
		[
			'playbackId' => $playbackId,
			'muxId' => $muxId,
		] = (new Query())
			->select('playbackId, muxId')
			->from(Mux::MUX_ASSETS_TABLE)
			->where(['assetId' => $asset->id])
			->one();

		if (empty($muxId))
			return null;

		if (empty($playbackId))
		{
			$playbackIds = $this->_api()->getAsset($muxId)->getData()->getPlaybackIds();

			if (empty($playbackIds))
				return null;

			$playbackId = @$playbackIds[0]->getId();

			Craft::$app->getDb()
				->createCommand()
				->update(
					Mux::MUX_ASSETS_TABLE,
					compact('playbackId'),
					compact('muxId'),
					[], false
				)
				->execute();
		}

		if (empty($playbackId))
			return null;

		return "https://stream.mux.com/$playbackId.m3u8";
	}

	public function getThumbnailUrl (Asset $asset):? string
	{
		$playbackId = (new Query())
			->select('playbackId')
			->from(Mux::MUX_ASSETS_TABLE)
			->where(['assetId' => $asset->id])
			->scalar();

		if (empty($playbackId))
			return null;

		return "https://image.mux.com/$playbackId/thumbnail.jpg";
	}

	private function _api ()
	{
		static $api;

		if ($api) return $api;

		$settings = Mux::getInstance()->getSettings();

		return $api = new AssetsApi(
			Craft::createGuzzleClient(),
			Configuration::getDefaultConfiguration()
				->setUsername(App::parseEnv($settings->accessTokenId))
				->setPassword(App::parseEnv($settings->secretKey))
		);
	}

}