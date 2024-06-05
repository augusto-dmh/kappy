import { v4 as uuidv4 } from "uuid";
class ApiError extends Error {
  constructor(type, title, status, message, detail) {
    super();
    this.type = type;
    this.title = title;
    this.status = status;
    this.message = message;
    this.detail = detail;
    this.requestId = uuidv4();
    this.subErrors = [];
  }
}

export default ApiError;
