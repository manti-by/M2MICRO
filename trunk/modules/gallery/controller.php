<?php
    defined('M2_MICRO') or die('Direct Access to this location is not allowed.');

    /**
     * Gallery Controller class
     * @name $galleryController
     * @package M2 Micro Framework
     * @subpackage Modules
     * @author Alexander Chaika
     * @since 0.2RC1
     */
    class GalleryController extends Controller {

        /**
         * Default gallery action
         * @param array $options
         * @return array $result
         */
        public function indexAction($options) {
            return $this->listAction($options);
        }

        /**
         * List gallery action
         * @param array $options
         * @return array $result
         */
        public function listAction($options) {
            // Get category ID
            $tags = System::getInstance()->getCmd('id');

            // Get category title
            if ($tags) {
                $options['data'] = $this->model->getGalleryByTags($tags);
                $options['title'] = 'Search by tags: ' . implode(', ', $tags);
            } else {
                $options['data'] = $this->model->getGallery();
                $options['title'] = Application::$config['site_title_' . Application::$config['language']];
            }

            // Get category items and render it
            $options['module'] = 'gallery';
            $options['body'] = $this->view->renderItemsArray($options);

            return $this->view->wrapSidebar($options);
        }

        /**
         * Runs updates in gallery
         */
        public function updateAction() {
            // Run actions and get stats
            $parsed = $this->model->updateFSList();
            $resized = $this->model->rebuildThumbnails();

            // Compile response
            $options['title'] = 'Gallery updates';
            $options['body']  = '<p>' . count($parsed) . ' ' . T('was parsed') . '</p><p>' . count($resized) . ' ' . T('was resized') . '</p>';

            return $options;
        }

        /**
         * Show gallery by id
         * @param array $options
         * @return array|bool $options
         */
        public function showAction($options) {
            // Get item ID
            $options['id'] = System::getInstance()->getCmd('id');

            // Get item title and data
            if ($options['id']) {
                $options['data'] = $this->model->getGalleryById($options['id']);
                $options['title'] = $options['data']->name;
            } else {
                return $this->view->_404($options);
            }

            // Render blog item
            if (!empty($options['data'])) {
                $options['body'] = $this->view->getContents('gallery', 'full', $options);
                return $this->view->wrapSidebar($options);
            } else {
                return $this->view->_404($options);
            }
        }

        /**
         * Track action for full gallery view
         * @param array $options
         * @return array|bool $options
         */
        public function trackAction($options) {
            // Set output and get item ID
            $options['output'] = View::OUTPUT_TYPE_JSON;
            $options['id'] = System::getInstance()->getCmd('id');

            if ($count = $this->model->trackGallery($options['id'])) {
                $options['data'] = array('result' => 'success', 'count' => $count);
            } else {
                $error = $this->getLastFromStack();
                $options['data'] = array('result' => 'error', 'error' => $error['message']);
            }

            return $options;
        }
    }
