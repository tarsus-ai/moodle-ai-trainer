@mod @mod_tarsuscoach
Feature: The administration pages of Tarsus
  In order to run Tarsus for the whole site
  As an administrator
  I need to see its status, the plan and the brains of the account

  Scenario: The status page in demo mode
    When I am on the "mod_tarsuscoach > Status" page logged in as "admin"
    Then I should see "Guardrails"
    And I should see "Mock mode (demo)"

  Scenario: The brains of the demo account
    When I am on the "mod_tarsuscoach > Brains" page logged in as "admin"
    Then I should see "mock-brain-default"

  Scenario: The plan and its features
    When I am on the "mod_tarsuscoach > Plan" page logged in as "admin"
    Then ".tarsus-table--features" "css_element" should exist
