class Base {
  constructor(type, title, status, message, detail, requestId, subErrors) {
    this.type = type;
    this.title = title;
    this.status = status;
    this.message = message;
    this.detail = detail;
    this.requestId = requestId;
    this.subErrors = subErrors;
  }

  *[Symbol.iterator]() {
    for (const value of Object.values(this)) {
      yield value;
    }
  }
}

export default Base;
