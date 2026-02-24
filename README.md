# Pass TUI
## Not affiliated with Proton or Proton Pass

### A terminal user interface to interact with the Proton Pass CLI.

_The Proton Pass CLI is still in beta. Use at your own risk._

### What this is
Pass TUI is a small, focused terminal UI that wraps the Proton Pass CLI. It trims down long CLI
commands into guided flows for common tasks like creating, viewing, and updating items.

### Requirements
- Works on all platforms (Linux, MacOS, Windows)
    - **NOTE: Windows users must run the application in a WSL environment.**
- Proton Pass paid subscription
- Proton Pass CLI installed and authenticated
- Familiarity with command line interfaces (shell, bash, etc.)
- PHP 8.2+

### Purpose
Proton Pass CLI has long commands that are difficult to remember.
Pass TUI aims to simplify the interaction with the Proton Pass CLI by providing a user-friendly
interface for creating or updating passwords and other sensitive information.

### Install Proton Pass CLI
- Install Proton Pass CLI from their official documentation, [found here](https://protonpass.github.io/pass-cli/get-started/installation/).

### Install Pass TUI

#### Option 1: Using the Standalone Executable (Recommended)
You can download the compiled executable from the [Releases page](https://github.com/jkimble/pass-tui/releases/).

1) Download the `pass-tui` file from the latest release.
2) Make the file executable:
   ```sh
   chmod +x pass-tui
   ```
3) (Optional) Move it to your PATH so you can run it from anywhere:
   ```sh
   sudo mv pass-tui /usr/local/bin/pass-tui
   ```
4) Run the app:
   ```sh
   pass-tui
   # Or ./pass-tui if you didn't move it to your PATH
   ```

#### Option 2: Running from Source
1) Clone this repository.
2) Install dependencies:
   ```sh
   composer install
   ```
3) Run the app:
   ```sh
   php pass-tui
   ```

### Usage notes
- You must be authenticated in the Proton Pass CLI before launching Pass TUI.
- **The app delegates all sensitive operations to the official CLI; data is _not_ stored by Pass TUI.**
- Currently, if the CLI reports an error, Pass TUI will surface it and stop the flow.

### Important notes
- This is a thin wrapper around the CLI, not a replacement.
- Feature availability depends on what the Proton Pass CLI currently supports.
- The CLI is in beta; expect breaking changes.

### Disclaimer
This project is not affiliated with or endorsed by Proton or Proton Pass.
