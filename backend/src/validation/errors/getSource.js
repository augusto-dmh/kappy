export default (trace) => {
  const srcError = /src(.*)/;
  const dependencyError = /node_modules.*$/;
  const traceFileName = trace[0].getFileName();
  const fileNameMatch =
    traceFileName.match(srcError) || traceFileName.match(dependencyError);

  const functionName = trace[0].getFunctionName();
  const fileName = fileNameMatch[0];
  const lineNumber = trace[0].getLineNumber();
  return { functionName, fileName, lineNumber };
};
