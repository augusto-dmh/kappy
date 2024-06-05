import { exec, execSync } from "child_process";
import { v4 as uuidv4 } from "uuid";
import getSource from "../validation/errors/getSource";

class Log {
  constructor(status, detail, trace, stack) {
    const { functionName, fileName, lineNumber } = getSource(trace);

    this.status = status;
    this.detail = detail;
    this.source = { functionName, fileName, lineNumber };
    this.build_info = process.versions.node;
    try {
      this.commitHash = execSync("git rev-parse HEAD").toString().trim();
    } catch (err) {
      this.commitHash = "No commit found (maybe not a git repository).";
    }
    this.requestId = uuidv4();
    this.stack = stack;
  }
}

export default Log;
