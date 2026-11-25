@mod @mod_tarsuscoach
Feature: The coach in a course
  In order to learn with the material of my course
  As a learner or a teacher
  I need to open the Tarsus coach with the modes the teacher chose

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | One      | teacher1@example.com |
      | student1 | Student   | One      | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "activities" exist:
      | activity    | name     | intro                  | course |
      | tarsuscoach | My coach | Questions about week 1 | C1     |

  Scenario: A learner opens the coach in demo mode
    When I am on the "My coach" "mod_tarsuscoach > View" page logged in as "student1"
    Then I should see "My coach"
    And I should see "Demo mode: the answers come from a built-in sample"
    And I should see "Ask a question" in the ".tarsus-mode-switch__group" "css_element"
    And I should see "Test knowledge" in the ".tarsus-mode-switch__group" "css_element"
    And I should see "Build knowledge" in the ".tarsus-mode-switch__group" "css_element"
    And I should see "Live interaction" in the ".tarsus-mode-switch__group" "css_element"
    And ".tarsus-teacher-bar" "css_element" should not exist

  Scenario: A teacher has the teacher tools
    When I am on the "My coach" "mod_tarsuscoach > View" page logged in as "teacher1"
    Then I should see "Settings" in the ".tarsus-teacher-bar" "css_element"

  Scenario: Only the modes the teacher chose are offered
    Given the following "activities" exist:
      | activity    | name     | course | testenabled | buildenabled | discussenabled |
      | tarsuscoach | Ask only | C1     | 0           | 0            | 0              |
    When I am on the "Ask only" "mod_tarsuscoach > View" page logged in as "student1"
    Then I should see "Ask a question" in the ".tarsus-mode-switch__group" "css_element"
    And I should not see "Test knowledge" in the ".tarsus-mode-switch__group" "css_element"
    And ".tarsus-live-bar" "css_element" should not exist
