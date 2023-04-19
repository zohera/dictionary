<?php
namespace Drupal\dictionary\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use GuzzleHttp\ClientInterface;
use Drupal\Component\Serialization\Json;
use Exception;

/**
 * @file
 * Form to input word and display its definition.
 */

 class DictionaryDefinitionForm extends FormBase {

  /**
   * The HTTP client to fetch the feed data with.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * Constructor for MymoduleServiceExample.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   *   A Guzzle client object.
   */
  public function __construct(ClientInterface $http_client) {
    $this->httpClient = $http_client;
  }

  /**
    * {@inheritdoc}
    */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('http_client')
    );
  }

  /**
   * @inheritdoc
   */
  public function getFormId() {
    return 'dictionary_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['word'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Word'),
      '#description' => $this->t('Enter a word to find its definition. Currently only English language is supported.'),
      '#maxlength' => 256,
      '#size' => 10,
      '#required' => TRUE,
    ];

    $form['find_word'] = [
      '#type' => 'submit',
      '#value' => $this->t('Find definition'),
      '#ajax' => [
        'callback' => '::findWordDefinition',
        'disable-refocus' => TRUE,
        'event' => 'click',
        'wrapper' => 'definition-container',
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Fetching definition ...'),
        ],
      ]
    ];

    $form['definition_output'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'definition-container']
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) { }

  /**
   * Function to get word definition through api call and return render array.
   */
  public function findWordDefinition(array &$form, FormStateInterface $form_state) {
    $word = trim($form_state->getValue('word'));

    if (!empty($word)) {
      try {
        $request = $this->httpClient->get('https://api.dictionaryapi.dev/api/v2/entries/en/' . $word);
        $response_content = $request->getBody()->getContents();
        $json_array = JSON::decode($response_content);

        // Move array by one position.
        $output = array_shift($json_array);

        // Validate that we have the right word.
        if (!empty($output['word']) && strtolower($output['word']) == strtolower($word)) {
          if (!empty($output['meanings'])) {
            // Reset previous output.
            $form['definition_output'] = [
              '#type' => 'container',
              '#attributes' => ['id' => 'definition-container']
            ];
            $response = [];

            foreach ($output['meanings'] as $meaning) {
              // Create one row per output.
              $response['definition_row'] = [
                '#type' => 'container',
                '#attributes' => [
                  'class' => [
                    'definition-row',
                  ]
                ],
              ];
              
              $part_of_speech = $meaning['partOfSpeech'];
              $definitions = $meaning['definitions'];
              
              // Display part of speech in a separte div.
              $response['definition_row']['definition_part_of_speech'] = [
                '#type' => 'container',
                '#attributes' => [
                  'class' => [
                    'definition-part-of-speech',
                  ]
                ],
              ];
              $response['definition_row']['definition_part_of_speech']['definition_type'] = [
                '#markup' => "<strong><em>$part_of_speech</em></strong>",
              ];

              // Display definition values in a separate div.
              $response['definition_row']['definition_values'] = [
                '#type' => 'container',
                '#attributes' => [
                  'class' => [
                    'definition-values',
                  ]
                ],
              ];

              foreach ($definitions as  $index => $definition_item) {
                $response['definition_row']['definition_values'][$index] = [
                  '#type' => 'container',
                  '#attributes' => [
                    'class' => [
                      'definition-value-item',
                    ]
                  ],
                ];
                $response['definition_row']['definition_values'][$index]['data'] = [
                  '#markup' => $definition_item['definition'],
                ];
              }

              // Add divider b/w each row.
              $response['definition_row']['divider'] = [
                '#markup' => "<hr/><br/>"
              ];

              $form['definition_output']['definition'][] = $response;
            }
          }
        }
      }
      // For unknown words, we get an exception, throw an error.
      catch (Exception $e) {
        $response = $this->t('Unable to find a definition for the given word. Please try a different word');
        $form['definition_output']['definition']['#markup'] = $response;
      }

      // If no response was set, then something wrong with the ajax response, throw error.
      if (empty($response)) {
        $response = $this->t('An error occurred trying to find a definition for the given word. Please try again later.');
        $form['definition_output']['definition']['#markup'] = $response;
      }
    }

    return $form['definition_output'];
  }
 }
