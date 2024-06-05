// only pure functions - handle the logic around send toasts outside.
import { z } from "zod";

export const isLengthValid = (value, min, max) => {
  const schema = z.coerce.string().min(min).max(max);
  return schema.safeParse(value).success;
};

export const isEmailValid = (email) => {
  const schema = z.coerce.string().email();
  return schema.safeParse(email).success;
};

export const isNumber = (value) => {
  const schema = z.coerce.number();
  return schema.safeParse(value).success;
};

export const isInteger = (value) => {
  const schema = z.coerce.number().int();
  return schema.safeParse(value).success;
};

export const isLatitude = (value) => {
  const schema = z.coerce.number().min(-90).max(90);
  return schema.safeParse(value).success;
};

export const isLongitude = (value) => {
  const schema = z.coerce.number().min(-180).max(180);
  return schema.safeParse(value).success;
};

// that's applied to non-string-type columns too - like 'age', because when they're not informed on creating/updating row routes where they should,
// they get during instantiation "" as value and are checkable with this validation. Then a "'field' is required" is thrown.
export const isNotEmpty = (value) => {
  const schema = z.coerce.string().min(1);
  return schema.safeParse(value).success;
};

export const itExists = async (value, field, Model) =>
  Model.findOne({
    where: { [field]: value },
  });
