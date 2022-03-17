<?php

namespace ether\mux;

use Craft;
use craft\base\Element;
use craft\base\Plugin;
use craft\elements\Asset;
use craft\events\DefineGqlTypeFieldsEvent;
use craft\events\ModelEvent;
use craft\gql\TypeManager;
use craft\helpers\ElementHelper;
use GraphQL\Type\Definition\Type;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use yii\base\Event;
use yii\base\Exception;

/**
 * @property Service $mux
 */
class Mux extends Plugin
{

	public const MUX_ASSETS_TABLE = '{{%mux_assets}}';
	public $hasCpSettings = true;

	public function init ()
	{
		parent::init();

		$this->setComponents([
			'mux' => Service::class,
		]);

		Event::on(
			Asset::class,
			Element::EVENT_AFTER_SAVE,
			[$this, 'onAfterAssetSave']
		);

		Event::on(
			Asset::class,
			Element::EVENT_AFTER_DELETE,
			[$this, 'onAfterAssetDelete']
		);

		Event::on(
			TypeManager::class,
			TypeManager::EVENT_DEFINE_GQL_TYPE_FIELDS,
			[$this, 'onDefineGqlTypeFields']
		);
	}

	// Settings
	// =========================================================================

	protected function createSettingsModel (): Settings
	{
		return new Settings();
	}

	/**
	 * @return bool|Settings|null
	 */
	public function getSettings (): ?Settings
	{
		return parent::getSettings();
	}

	/**
	 * @return string|null
	 * @throws LoaderError
	 * @throws RuntimeError
	 * @throws SyntaxError
	 * @throws Exception
	 */
	protected function settingsHtml (): ?string
	{
		return Craft::$app->getView()->renderTemplate('mux/_settings', [
			'settings' => $this->getSettings(),
		]);
	}

	// Events
	// =========================================================================

	public function onAfterAssetSave (ModelEvent $event)
	{
		/** @var Asset $asset */
		$asset = $event->sender;

		if (ElementHelper::isDraftOrRevision($asset) || !$event->isNew)
			return;

		if ($asset->kind !== 'video')
			return;

		$this->mux->createMuxFromAsset($asset);
	}

	public function onAfterAssetDelete (Event $event)
	{
		/** @var Asset $asset */
		$asset = $event->sender;

		if (!strpos($asset->getMimeType(), 'video'))
			return;

		$this->mux->deleteMuxFromAsset($asset);
	}

	public function onDefineGqlTypeFields (DefineGqlTypeFieldsEvent $event)
	{
		if ($event->typeName !== 'AssetInterface')
			return;

		$self = $this;

		$event->fields['playbackUrl'] = [
			'name' => 'playbackUrl',
			'type' => Type::string(),
			'resolve' => function (Asset $source) use ($self) {
				return $self->mux->getPlaybackUrl($source);
			},
		];

		$event->fields['thumbnailUrl'] = [
			'name' => 'thumbnailUrl',
			'type' => Type::string(),
			'resolve' => function (Asset $source) use ($self) {
				return $self->mux->getThumbnailUrl($source);
			},
		];
	}

}