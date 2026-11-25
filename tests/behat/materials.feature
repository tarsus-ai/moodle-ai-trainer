@mod @mod_tarsuscoach
Feature: The activity's form and the teacher's list of the course material
  In order to choose what the coach knows
  As a teacher
  I need to add the coach to a course and see the course's material

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | One      | teacher1@example.com |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |

  Scenario: A teacher adds the coach with the activity form
    Given I log in as "teacher1"
    When I add a tarsuscoach activity to course "Course 1" section "1" and I fill the form with:
      | Name        | Coach from the form    |
      | Description | Questions about week 2 |
    And I am on the "Coach from the form" "mod_tarsuscoach > View" page
    Then I should see "Ask a question" in the ".tarsus-mode-switch__group" "css_element"

  Scenario: The course's material is listed for the teacher
    Given the following "activities" exist:
      | activity    | name         | intro    | course |
      | tarsuscoach | My coach     | A coach  | C1     |
      | page        | Week 1 notes | The week | C1     |
    When I am on the "My coach" "mod_tarsuscoach > Materials" page logged in as "teacher1"
    Then I should see "Week 1 notes" in the ".tarsus-table" "css_element"
