<?php

namespace Drupal\social_group\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\social_core\Entity\Element\EntityAutocomplete;
use Drupal\Component\Utility\Tags;

/**
 * Provides an Group member autocomplete form element.
 *
 * The #default_value accepted by this element is either an entity object or an
 * array of entity objects.
 *
 * @FormElement("social_group_entity_autocomplete")
 */
class SocialGroupEntityAutocomplete extends EntityAutocomplete {

  /**
   * Form element validation handler for entity_autocomplete elements.
   */
  public static function validateEntityAutocomplete(array &$element, FormStateInterface $form_state, array &$complete_form) {
    // For other types not using members, we have entity_id as multiple
    // options. For example adding multiple members to a group.
    $input_values = Tags::explode($element['#value']);

    foreach ($input_values as $input) {
      $match = static::extractEntityIdFromAutocompleteInput($input);
      if ($match === NULL) {
        // Try to get a match from the input string when the user didn't use
        // the autocomplete but filled in a value manually.
        $match = static::matchEntityByTitle($handler, $input, $element, $form_state, !$autocreate);
      }

      if ($match !== NULL) {
        $value[$match] = [
          'target_id' => $match,
        ];

        // Validate input for every single user. This way we make sure that
        // The element validates one, two or more users added in the autocomplete.
        // This is because Group doesnt allow adding multiple users at once,
        // so we need to validate single users, if they all pass we can add
        // them all in the _social_group_action_form_submit.

        $form_state->setValue('entity_id', $value[$match]['target_id']);
        parent::validateEntityAutocomplete($element, $form_state, $complete_form);
      }
    }

    if ($value !== null) {
      $form_state->setValue('entity_id_new', $value);
    }

//    parent::validateEntityAutocomplete($element, $form_state, $complete_form);
  }

}
