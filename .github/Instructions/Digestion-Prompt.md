#codebase


Hello. You are about to act as an automated "Codebase Digest Agent". Your goal is to update my project's documentation based on a range of commits. You will operate in a strict, step-by-step loop that you manage yourself.

**Your Core Instructions:**

1.  You will process a list of pull requests one by one.

2.  After every single action you take (like fetching a diff, or updating a file), you MUST end your response by stating your **Current State** and your **Next Action**. This is the most important rule.

3.  I will not prompt you for each step. You will follow the plan laid out below, prompting yourself with the "Next Action" from your previous response. I will only intervene if you make a mistake or get stuck.


----------

### **The Master Plan**

**Phase 1: Initialization**

1.  Identify the pull requests to be processed between commits `d492f0e1` and `3bb29d4e`. Use the @terminal to run the gh command to get a list of PR numbers and titles.

2.  Present this list to me in a checklist format, which will serve as our **State Tracker**.


**Phase 2: The Processing Loop**  
You will now loop through each PR from the State Tracker. For each PR:

1.  **Announce:** State which PR you are now processing (e.g., "Processing PR #42...").

2.  **Get Diff:** Use the @terminal to get the git diff for that specific PR number.

3.  **Update DIGEST_DATA_MODELS.md:** Analyze the diff for changes in database/migrations/ or app/Models/. Propose the necessary changes for the @workspace /file:docs/DIGEST_DATA_MODELS.md file. If there are no changes, state that and move on.

4.  **Update DIGEST_CORE_LOGIC.md:** Analyze the diff for changes in app/Actions/ or app/Services/. Propose changes for the @workspace /file:docs/DIGEST_CORE_LOGIC.md file.

5.  **Update DIGEST_API_INTERFACES.md:** Analyze the diff for changes in routes/ or app/Http/Controllers/. Propose changes for the @workspace /file:docs/DIGEST_API_INTERFACES.md file.

6.  **Update State:** Mark the current PR as complete in the State Tracker.


**Phase 3: Finalization**

1.  Once all PRs in the State Tracker are complete, announce that the loop has finished.

2.  Perform a final review of all the proposed changes.

3.  Generate a high-level summary of all changes and propose adding it to the main @workspace /file:docs/CODEBASE_DIGEST.md file.

4.  Announce that the task is complete.


----------

To begin, please start with **Phase 1, Step 1**. I am ready.
