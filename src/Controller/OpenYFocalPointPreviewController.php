<?php

namespace Drupal\openy_focal_point\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Image\ImageFactory;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\focal_point\Controller\FocalPointPreviewController;
use Drupal\image\ImageEffectManager;
use Drupal\openy_focal_point\Form\OpenYFocalPointCropForm;
use Drupal\openy_focal_point\Form\OpenYFocalPointEditForm;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Exception\InvalidArgumentException;

/**
 * Class OpenYFocalPointPreviewController. We display only styles that are
 * going to be used by the formatter instead of all styles that use focal_point.
 *
 * @package Drupal\focal_point\Controller
 */
class OpenYFocalPointPreviewController extends FocalPointPreviewController {

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * {@inheritdoc}
   * @param \Drupal\Core\Image\ImageFactory $image_factory
   *   The image_factory parameter.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request parameter.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger
   *   The logger factory.
   * @param \Drupal\image\ImageEffectManager $imageEffectManager
   *   The image effect manager.
   * @param \Drupal\Core\Entity\EntityStorageInterface $fileStorage
   *   The file storage service.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   */
  public function __construct(
    ImageFactory $image_factory,
    RequestStack $request_stack,
    LoggerChannelFactoryInterface $logger,
    ImageEffectManager $imageEffectManager,
    EntityStorageInterface $fileStorage,
    RendererInterface $renderer
  ) {
    parent::__construct($image_factory, $request_stack, $logger, $imageEffectManager, $fileStorage);
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('image.factory'),
      $container->get('request_stack'),
      $container->get('logger.factory'),
      $container->get('plugin.manager.image.effect'),
      $container->get('entity_type.manager')->getStorage('file'),
      $container->get('renderer'),
    );
  }

  public function getFocalPointImageStyle() {
    $style = $this->request->get('image_style');
    return $this->entityTypeManager()->getStorage('image_style')->load($style);
  }

  /**
   * Callback to edit Manual Crop.
   */
  public function editCropContent($fid, $focal_point_value) {
    return $this->renderFormInDialog($fid, $focal_point_value, OpenYFocalPointCropForm::class, $this->t('Manual Crop'));
  }

  /**
   * Callback to set Focal Point.
   */
  public function editFocalPointContent($fid, $focal_point_value) {
    return $this->renderFormInDialog($fid, $focal_point_value, OpenYFocalPointEditForm::class, $this->t('Edit Focal Point'));
  }

  /**
   * Prepare ajax command displaying dialog with form.
   */
  protected function renderFormInDialog($fid, $focal_point_value, $form_class_name, $dialog_title) {
    $file = $this->fileStorage->load($fid);
    $image = $this->imageFactory->get($file->getFileUri());
    if (!$image->isValid()) {
      throw new InvalidArgumentException('The file with id = $fid is not an image.');
    }

    $style = $this->getFocalPointImageStyle();

    // Since we are about to create a new preview of this image, we first must
    // flush the old one. This should not be a performance hit since there is
    // no good reason for anyone to preview an image unless they are changing
    // the focal point value.
    image_path_flush($image->getSource());

    $form = \Drupal::formBuilder()->getForm($form_class_name, $file, $style, $focal_point_value);
    $html = $this->renderer->render($form);

    $options = [
      'dialogClass' => 'popup-dialog-class',
      'width' => '80%',
    ];
    $response = new AjaxResponse();
    $response->addCommand(
      new OpenModalDialogCommand($dialog_title, $html, $options)
    );

    return $response;
  }

}
